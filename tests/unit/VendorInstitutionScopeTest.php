<?php

namespace Tests\Unit;

use App\Services\VendorInstitutionScope;
use App\Services\VendorApiKeyService;
use PHPUnit\Framework\TestCase;

final class VendorInstitutionScopeTest extends TestCase
{
    private function scope(array $rules): VendorInstitutionScope
    {
        return new VendorInstitutionScope(['institution_access_mode' => 'restricted', 'institution_access_rules' => json_encode($rules)]);
    }

    public static function categories(): iterable
    {
        foreach (VendorInstitutionScope::FIELDS as $category => $fields) {
            yield $category => [$category, $fields];
        }
    }

    /** @dataProvider categories */
    public function testSpecificInstitutionAlwaysBelongsToItsUniversity(string $category, array $fields): void
    {
        [$university, $institution] = $fields;
        $scope = $this->scope([['university_code' => 'u-1234', 'institution_code' => '001']]);
        self::assertTrue($scope->allows($category, [$university => ' U-1234 ', $institution => '001']));
        self::assertFalse($scope->allows($category, [$university => 'U-9999', $institution => '001']));
        self::assertFalse($scope->allows($category, [$university => 'U-1234', $institution => '002']));
        self::assertFalse($scope->allows($category, [$university => 'U-1234', $institution => '1']));
        self::assertFalse($scope->allows($category, [$university => 'U-1234', $institution => 'NA']));
    }

    public function testWholeUniversityRuleDoesNotGrantOtherUniversities(): void
    {
        $scope = $this->scope([['university_code' => 'U-1234', 'institution_code' => null]]);
        self::assertTrue($scope->allows('admissions', ['univ_code' => 'U-1234', 'college_code' => 'any-code']));
        self::assertTrue($scope->allowsUniversity('u-1234'));
        self::assertFalse($scope->allowsUniversity('U-9999'));
        self::assertFalse($scope->allows('admissions', ['univ_code' => 'U-9999', 'college_code' => 'any-code']));
    }

    public function testMalformedRestrictedScopeNeverGrantsAccess(): void
    {
        foreach (['not-json', 'null', '"U-1234"', '[]', '[{"university_code":"*"}]'] as $rules) {
            $scope = new VendorInstitutionScope(['institution_access_mode' => 'restricted', 'institution_access_rules' => $rules]);
            self::assertFalse($scope->allowsUniversity('U-1234'));
            self::assertFalse($scope->allows('admissions', ['univ_code' => 'U-1234', 'college_code' => '001']));
            self::assertNotSame('', $scope->key());
        }
        self::assertFalse((new VendorInstitutionScope(['institution_access_mode' => 'invalid']))->allowsUniversity('U-1234'));
    }

    public function testScopeFingerprintIsCanonicalAndChangesWithPermissions(): void
    {
        $a = ['university_code' => 'U-1234', 'institution_code' => 'C-001'];
        $b = ['university_code' => 'U-4567', 'institution_code' => 'C-002'];
        self::assertSame($this->scope([$a, $b])->key(), $this->scope([$b, $a, $a])->key());
        self::assertNotSame($this->scope([$a])->key(), $this->scope([$b])->key());
        self::assertSame('', (new VendorInstitutionScope(['institution_access_mode' => 'all']))->key());
        self::assertSame(array_keys(VendorApiKeyService::CATEGORIES), array_keys(VendorInstitutionScope::FIELDS));
    }

    public function testUniversityWideRuleSupersedesNarrowerRules(): void
    {
        self::assertSame([['university_code' => 'U-1234', 'institution_code' => null]], VendorInstitutionScope::canonicalRules([
            ['university_code' => 'U-1234', 'institution_code' => '001'],
            ['university_code' => ' u-1234 ', 'institution_code' => ''],
        ]));
    }

    public function testClientCannotSeedAnIdempotentResponseWithAnotherScope(): void
    {
        $scope = $this->scope([['university_code' => 'U-1234', 'institution_code' => '001']]);
        $payload = ['PageNo' => 1, '__institution_scope_key' => $scope->key()];
        $all = new VendorInstitutionScope(['institution_access_mode' => 'all']);
        self::assertSame(['PageNo' => 1], $all->idempotencyPayload($payload));
        self::assertSame($scope->key(), $scope->idempotencyPayload(['__institution_scope_key' => 'spoofed'])['__institution_scope_key']);
        self::assertNotSame($all->idempotencyPayload($payload), $scope->idempotencyPayload($payload));
    }

    public function testWildcardUniversityCodesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VendorInstitutionScope::canonicalRules([['university_code' => '*', 'institution_code' => '001']]);
    }
}
