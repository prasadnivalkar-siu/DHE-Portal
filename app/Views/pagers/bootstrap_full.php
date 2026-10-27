<?php

use CodeIgniter\Pager\PagerRenderer;

/** @var PagerRenderer $pager */
$pager->setSurroundCount(2);
?>
<nav aria-label="Page navigation">
    <ul class="pagination pagination-sm">
        <?php if ($pager->hasPrevious()): ?>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getFirst()) ?>" aria-label="First page">First</a></li>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getPrevious()) ?>" aria-label="Previous page">Previous</a></li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link): ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>>
                <a class="page-link" href="<?= esc($link['uri']) ?>"><?= esc($link['title']) ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNext()): ?>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getNext()) ?>" aria-label="Next page">Next</a></li>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getLast()) ?>" aria-label="Last page">Last</a></li>
        <?php endif ?>
    </ul>
</nav>
