<?php
$keyExpires = $vendor['api_key_expires_at'] ?? null;
$keyLive = \App\Libraries\VendorKeyPolicy::usable($keyExpires);
$keySoon = $keyLive && $keyExpires !== null && $keyExpires <= date('Y-m-d H:i:s', time() + 7 * 86400);
$overlapEnd = $vendor['previous_key_valid_until'] ?? null;
?>
<small class="table-subtext">Expires: <?= esc($keyExpires ?? 'No expiry set') ?></small>
<?php if (! $keyLive): ?><span class="status status-failed mt-1">Expired</span><?php elseif ($keySoon): ?><span class="status status-pending mt-1">Expires within 7 days</span><?php endif ?>
<?php if ($overlapEnd !== null): ?><small class="table-subtext"><?= \App\Libraries\VendorKeyPolicy::usable($overlapEnd) ? 'Previous key accepted until ' : 'Previous key ended ' ?><?= esc($overlapEnd) ?></small><?php endif ?>
