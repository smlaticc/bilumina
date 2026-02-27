<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Services/Cache.php';
require_once __DIR__ . '/../src/Services/ApiClient.php';
require_once __DIR__ . '/../src/Services/GroupService.php';
require_once __DIR__ . '/../src/Services/ItemService.php';
require_once __DIR__ . '/../src/Utils/Formatter.php';
require_once __DIR__ . '/../src/Utils/Html.php';

$config = require __DIR__ . '/../src/config.php';

$cache = new Cache($config['cache']['dir'], (int)$config['cache']['ttl_seconds']);
$api = new ApiClient(
    $config['api']['baseUrl'],
    $config['api']['key'],
    (int)$config['api']['timeout_seconds'],
    $cache
);

$groupService = new GroupService($api);
$itemService  = new ItemService($api);

$error = null;
$groupsTree = [];
$items = [];

try {
    $groupService->load();
    $groupsTree = $groupService->getTree();
    $items = $itemService->load();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$groupId = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$itemId  = isset($_GET['item'])  ? (int)$_GET['item']  : 0;

$min = isset($_GET['min']) && $_GET['min'] !== '' ? (float)str_replace(',', '.', (string)$_GET['min']) : null;
$max = isset($_GET['max']) && $_GET['max'] !== '' ? (float)str_replace(',', '.', (string)$_GET['max']) : null;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per  = isset($_GET['per'])  ? max(6, min(60, (int)$_GET['per'])) : 18;

function izpisiSkupine(array $nodes, int $activeId): void {
    echo "<ul class='list-unstyled ms-2'>";
    foreach ($nodes as $n) {
        $id = (int)$n['id'];
        $isActive = $id === $activeId;
        echo "<li class='mb-1'>";
        echo "<a class='text-decoration-none " . ($isActive ? "fw-bold" : "") . "' href='?group={$id}'>"
            . Html::e($n['name']) . "</a>";
        if (!empty($n['children'])) {
            izpisiSkupine($n['children'], $activeId);
        }
        echo "</li>";
    }
    echo "</ul>";
}

function ustvariSeznamUrl(?int $groupId, ?float $min, ?float $max, int $page, int $per): string {
    $q = [];
    if ($groupId && $groupId > 0) $q['group'] = (string)$groupId;
    if ($min !== null) $q['min'] = (string)$min;
    if ($max !== null) $q['max'] = (string)$max;
    $q['page'] = (string)$page;
    $q['per']  = (string)$per;
    return '?' . http_build_query($q);
}

function ustvariArtikelUrl(int $itemId, ?int $groupId, ?float $min, ?float $max, int $page, int $per): string {
    $q = [];
    $q['item'] = (string)$itemId;
    if ($groupId && $groupId > 0) $q['group'] = (string)$groupId;
    if ($min !== null) $q['min'] = (string)$min;
    if ($max !== null) $q['max'] = (string)$max;
    $q['page'] = (string)$page;
    $q['per']  = (string)$per;
    return '?' . http_build_query($q);
}
?>
<!doctype html>
<html lang="sl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trgovina</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .sidebar { max-height: 80vh; overflow: auto; }
    .stara-cena { text-decoration: line-through; opacity: .7; }
    .oznaka-popust { font-size: .85rem; }
    .card-img-top { object-fit: cover; height: 180px; background: #f5f5f5; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/">Mini trgovina</a>
  </div>
</nav>

<div class="container my-4">
  <?php if ($error): ?>
    <div class="alert alert-warning">
      <div class="fw-bold">Opozorilo</div>
      <div><?= Html::e($error) ?></div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <aside class="col-12 col-lg-3">
      <div class="card">
        <div class="card-header fw-bold">Skupine</div>
        <div class="card-body sidebar">
          <?php if (!$groupsTree): ?>
            <div class="text-muted">Ni podatkov.</div>
          <?php else: ?>
            <?php izpisiSkupine($groupsTree, $groupId); ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header fw-bold">Filter cene</div>
        <div class="card-body">
          <form method="get" class="row g-2">
            <?php if ($groupId > 0): ?>
              <input type="hidden" name="group" value="<?= (int)$groupId ?>">
            <?php endif; ?>
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per" value="<?= (int)$per ?>">
            <div class="col-6">
              <label class="form-label">Min</label>
              <input class="form-control" name="min" value="<?= Html::e($_GET['min'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Max</label>
              <input class="form-control" name="max" value="<?= Html::e($_GET['max'] ?? '') ?>">
            </div>
            <div class="col-12 d-grid">
              <button class="btn btn-primary">Uporabi</button>
            </div>
          </form>
        </div>
      </div>
    </aside>

    <main class="col-12 col-lg-9">
      <?php if ($itemId > 0): ?>
        <?php $item = $itemService->findById($items, $itemId); ?>
        <?php if (!$item): ?>
          <div class="alert alert-danger">Artikel ni najden.</div>
        <?php else: ?>
          <?php
            $img = $item['imageUrl'] ?? null;
            $discount = Formatter::percentDiscount($item['priceOld'] ?? null, $item['price'] ?? null);
            $nazaj = ustvariSeznamUrl($groupId ?: null, $min, $max, $page, $per);
          ?>
        <div class="card">
          <div class="card-body">
            <a href="<?= Html::e($nazaj) ?>">&larr; Nazaj</a>
            <div class="row mt-3">
              <div class="col-12 col-md-5">
                <?php if ($img): ?>
                  <img class="img-fluid rounded border" src="<?= Html::e($img) ?>" alt="<?= Html::e($item['name']) ?>">
                <?php else: ?>
                  <div class="border rounded d-flex align-items-center justify-content-center" style="height:300px;">
                    <span class="text-muted">Ni slike</span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="col-12 col-md-7">
                <h3><?= Html::e($item['name']) ?></h3>

                <div class="mb-3">
                  <span class="fs-4 fw-bold"><?= Html::e(Formatter::price((float)$item['price'])) ?></span>

                  <?php if (!empty($item['priceOld'])): ?>
                    <span class="stara-cena ms-2"><?= Html::e(Formatter::price((float)$item['priceOld'])) ?></span>
                  <?php endif; ?>

                  <?php if ($discount !== null): ?>
                    <span class="badge text-bg-danger ms-2">-<?= (int)$discount ?>%</span>
                  <?php endif; ?>
                </div>

                <div class="row g-2 mb-3">
                  <?php if (!empty($item['sku'])): ?>
                    <div class="col-6">
                      <div class="text-muted small">Šifra</div>
                      <div class="mono"><?= Html::e($item['sku']) ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($item['brand'])): ?>
                    <div class="col-6">
                      <div class="text-muted small">Znamka</div>
                      <div><?= Html::e($item['brand']) ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($item['color'])): ?>
                    <div class="col-6">
                      <div class="text-muted small">Barva</div>
                      <div><?= Html::e($item['color']) ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if ($item['stock'] !== null): ?>
                    <div class="col-6">
                      <div class="text-muted small">Zaloga</div>
                      <div><?= (int)$item['stock'] > 0 ? 'Na zalogi' : 'Ni na zalogi' ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($item['groupName'])): ?>
                    <div class="col-12">
                      <div class="text-muted small">Skupina</div>
                      <div><?= Html::e($item['groupName']) ?></div>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($item['description'])): ?>
                  <hr>
                  <div>
                    <?= $item['description'] ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      <?php else: ?>
        <?php
          $filtered = $items;
          if ($groupId > 0 && $groupService->exists($groupId)) {
              $ids = $groupService->getDescendantIds($groupId);
              $filtered = $itemService->inGroups($filtered, $ids);
          }
          if ($min !== null || $max !== null) {
              $filtered = $itemService->filterByPrice($filtered, $min, $max);
          }

          $total = count($filtered);
          $totalPages = max(1, (int)ceil($total / $per));
          $page = min($page, $totalPages);
          $offset = ($page - 1) * $per;
          $paged = array_slice($filtered, $offset, $per);
        ?>
        <div class="card">
          <div class="card-body">
            <div class="mb-2">
              Prikazano <?= (int)count($paged) ?> od <?= (int)$total ?>
            </div>
            <?php if ($total === 0): ?>
              <div class="text-muted">Ni artiklov.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach ($paged as $it): ?>
                  <?php
                    $img = $it['imageUrl'] ?? null;
                    $discount = Formatter::percentDiscount($it['priceOld'] ?? null, $it['price'] ?? null);
                    $link = ustvariArtikelUrl((int)$it['id'], $groupId ?: null, $min, $max, $page, $per);
                  ?>
                  <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100">
                      <?php if ($img): ?>
                        <img class="card-img-top" src="<?= Html::e($img) ?>">
                      <?php endif; ?>
                      <div class="card-body d-flex flex-column">
                        <div class="fw-bold mb-1"><?= Html::e($it['name']) ?></div>
                        <div>
                          <span class="fw-bold"><?= Html::e(Formatter::price((float)$it['price'])) ?></span>
                          <?php if (!empty($it['priceOld'])): ?>
                            <span class="stara-cena ms-2"><?= Html::e(Formatter::price((float)$it['priceOld'])) ?></span>
                          <?php endif; ?>
                          <?php if ($discount !== null): ?>
                            <span class="badge text-bg-danger oznaka-popust ms-2">-<?= (int)$discount ?>%</span>
                          <?php endif; ?>
                        </div>
                        <div class="mt-auto d-grid mt-2">
                          <a class="btn btn-outline-primary" href="<?= Html::e($link) ?>">Podrobnosti</a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <nav class="mt-3">
                <ul class="pagination pagination-sm">
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                      <a class="page-link" href="<?= Html::e(ustvariSeznamUrl($groupId ?: null, $min, $max, $p, $per)) ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>