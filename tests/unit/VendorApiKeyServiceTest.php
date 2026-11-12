<?php

namespace Tests\Unit;

use App\Models\ApiVendorModel;
use App\Services\VendorApiKeyService;
use App\Services\VendorApiContext;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Encryption\Encryption;
use CodeIgniter\Encryption\EncrypterInterface;

final class VendorApiKeyServiceTest extends CIUnitTestCase
{
    private function encrypter(string $key = 'test-encryption-key-32-bytes-long!'): EncrypterInterface
    {
        $config = new \Config\Encryption();
        $config->key = $key;
        return (new Encryption($config))->initialize($config);
    }

    public function testKeysContain256BitsOfRandomnessAndAnEncryptedCopy(): void
    {
        $service = new VendorApiKeyService(null, $this->encrypter());
        $first = $service->generate();
        $second = $service->generate();
        self::assertMatchesRegularExpression('/^dhe_[A-Za-z0-9_-]{43}$/', $first['key']);
        self::assertSame(hash('sha256', $first['key']), $first['api_key_hash']);
        self::assertSame(substr($first['key'], 0, 12), $first['api_key_prefix']);
        self::assertNotSame($first['key'], $second['key']);
        self::assertNotSame($first['api_key_hash'], $second['api_key_hash']);
        self::assertStringNotContainsString($first['key'], $first['api_key_encrypted']);
        self::assertSame($first['key'], $service->reveal($first));
    }

    public function testOnlyTheCurrentKeyAuthenticatesAndInactiveVendorsAreRejected(): void
    {
        $model = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['findByKeyHash'])->getMock();
        $service = new VendorApiKeyService($model, $this->encrypter());
        $first = $service->generate();
        $second = $service->generate();
        $vendor = ['id' => 7, 'vendor_code' => 'vendor-a', 'is_active' => 1, 'api_key_hash' => $second['api_key_hash']];
        $model->method('findByKeyHash')->willReturnCallback(static fn (string $hash): ?array => $hash === $second['api_key_hash'] ? $vendor : null);
        self::assertNull($service->authenticate($first['key']));
        self::assertSame('vendor-a', $service->authenticate($second['key'])['vendor_code']);
        self::assertNull($service->authenticate(''));
        self::assertNull($service->authenticate(str_repeat('x', 257)));

        $inactiveModel = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['findByKeyHash'])->getMock();
        $inactiveModel->method('findByKeyHash')->willReturn(array_replace($vendor, ['is_active' => 0]));
        self::assertNull((new VendorApiKeyService($inactiveModel))->authenticate($second['key']));
    }

    public function testContextCannotReuseThePreviousIdentityAfterReset(): void
    {
        $context = new VendorApiContext();
        $context->set(['vendor_code' => 'vendor-a', 'rate_limit_per_minute' => 15]);
        self::assertSame('vendor-a', $context->vendorCode());
        self::assertSame(15, $context->rateLimit());
        $context->set(null);
        $this->expectException(\LogicException::class);
        $context->vendorCode();
    }

    public function testBothKeysAuthenticateDuringOverlapUsingTheSameVendorIdentity(): void
    {
        $old = 'old-key-for-unit-test'; $new = 'new-key-for-unit-test';
        $vendor = ['id' => 7, 'vendor_code' => 'vendor-a', 'is_active' => 1, 'api_key_hash' => hash('sha256', $new), 'api_key_expires_at' => '2099-01-01 00:00:00', 'previous_api_key_hash' => hash('sha256', $old), 'previous_key_valid_until' => '2098-01-01 00:00:00'];
        $model = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['findByKeyHash'])->getMock();
        $model->method('findByKeyHash')->willReturn($vendor);
        $service = new VendorApiKeyService($model);
        self::assertSame('current', $service->authenticate($new)['_authenticated_key_slot']);
        self::assertSame('previous', $service->authenticate($old)['_authenticated_key_slot']);
        self::assertSame('vendor-a', $service->authenticate($old)['vendor_code']);
        self::assertSame('2098-01-01 00:00:00', $service->authenticate($old)['_authenticated_key_expires_at']);
        self::assertNull($service->authenticate('another-key'));
    }

    public static function expiredCredentials(): iterable
    {
        yield 'expired current' => ['current-key', ['api_key_expires_at' => '2000-01-01 00:00:00']];
        yield 'expired previous' => ['previous-key', ['previous_key_valid_until' => '2000-01-01 00:00:00']];
        yield 'previous missing deadline' => ['previous-key', ['previous_key_valid_until' => null]];
        yield 'invalid previous deadline' => ['previous-key', ['previous_key_valid_until' => 'invalid']];
        yield 'inactive current' => ['current-key', ['is_active' => 0]];
        yield 'inactive previous' => ['previous-key', ['is_active' => 0]];
    }

    /** @dataProvider expiredCredentials */
    public function testExpiredRevokedAndInactiveCredentialsAreDenied(string $key, array $changes): void
    {
        $vendor = array_replace(['id' => 7, 'is_active' => 1, 'api_key_hash' => hash('sha256', 'current-key'), 'api_key_expires_at' => null, 'previous_api_key_hash' => hash('sha256', 'previous-key'), 'previous_key_valid_until' => '2099-01-01 00:00:00'], $changes);
        $model = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['findByKeyHash'])->getMock();
        $model->method('findByKeyHash')->willReturn($vendor);
        self::assertNull((new VendorApiKeyService($model))->authenticate($key));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $service = new VendorApiKeyService(null, $this->encrypter());
        $vendor = $service->generate();
        $ciphertext = base64_decode($vendor['api_key_encrypted']);
        $ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
        $vendor['api_key_encrypted'] = base64_encode($ciphertext);
        $this->expectException(\CodeIgniter\Encryption\Exceptions\EncryptionException::class);
        $service->reveal($vendor);
    }

    public function testCiphertextMustMatchTheCurrentKeyHash(): void
    {
        $service = new VendorApiKeyService(null, $this->encrypter());
        $vendor = $service->generate();
        $vendor['api_key_hash'] = hash('sha256', 'a different current API key');
        $this->expectException(\RuntimeException::class);
        $service->reveal($vendor);
    }

    public function testWrongEncryptionKeyCannotRevealTheCredential(): void
    {
        $service = new VendorApiKeyService(null, $this->encrypter());
        $vendor = $service->generate();
        $wrongService = new VendorApiKeyService(null, $this->encrypter(str_repeat('x', 32)));
        $this->expectException(\CodeIgniter\Encryption\Exceptions\EncryptionException::class);
        $wrongService->reveal($vendor);
    }

    public function testUnknownHashOnlyKeyCannotBeRecoveredOrChanged(): void
    {
        $model = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['storeEncryptedKey'])->getMock();
        $model->expects(self::never())->method('storeEncryptedKey');
        $service = new VendorApiKeyService($model, $this->encrypter());
        self::assertNull($service->reveal(['id' => 7, 'api_key_hash' => hash('sha256', 'unavailable-older-vendor-key')]));
    }

    public function testKnownLegacyKeyIsRecoveredOnlyWhenItsHashMatches(): void
    {
        $previous = [getenv('dhe.apiKey'), $_ENV['dhe.apiKey'] ?? null, $_SERVER['dhe.apiKey'] ?? null];
        $key = 'a-test-legacy-key-not-used-for-production';
        putenv('dhe.apiKey=' . $key);
        $_ENV['dhe.apiKey'] = $_SERVER['dhe.apiKey'] = $key;
        try {
            $model = $this->getMockBuilder(ApiVendorModel::class)->disableOriginalConstructor()->onlyMethods(['storeEncryptedKey'])->getMock();
            $model->expects(self::once())->method('storeEncryptedKey')->with(7, hash('sha256', $key), self::callback(static fn (string $encrypted): bool => ! str_contains($encrypted, $key)))->willReturn(true);
            $service = new VendorApiKeyService($model, $this->encrypter());
            self::assertSame($key, $service->reveal(['id' => 7, 'api_key_hash' => hash('sha256', $key)]));
            self::assertNull($service->reveal(['id' => 7, 'api_key_hash' => hash('sha256', 'a-regenerated-key')]));
        } finally {
            $previous[0] === false ? putenv('dhe.apiKey') : putenv('dhe.apiKey=' . $previous[0]);
            if ($previous[1] === null) { unset($_ENV['dhe.apiKey']); } else { $_ENV['dhe.apiKey'] = $previous[1]; }
            if ($previous[2] === null) { unset($_SERVER['dhe.apiKey']); } else { $_SERVER['dhe.apiKey'] = $previous[2]; }
        }
    }
}
