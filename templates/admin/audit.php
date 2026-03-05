<?php
use Platform\Enum\AuditAction;

$filtres = $filtres ?? ['date_debut' => '', 'date_fin' => '', 'action' => '', 'utilisateur' => '', 'ip' => ''];
$filtreParams = array_filter($filtres, fn($v) => $v !== '');
$filtreQueryString = $filtreParams ? '&' . http_build_query($filtreParams) : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-journal-text me-2"></i>Journal d'audit</h2>
    <?php if (!empty($logs)): ?>
    <a href="/admin/audit/export-csv?<?= http_build_query($filtreParams) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-download me-1"></i>Exporter CSV
    </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/audit" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label for="filtre-date-debut" class="form-label small mb-1">Du</label>
            <input type="date" class="form-control form-control-sm" id="filtre-date-debut" name="date_debut"
                   value="<?= htmlspecialchars($filtres['date_debut']) ?>">
        </div>
        <div class="col-md-2">
            <label for="filtre-date-fin" class="form-label small mb-1">Au</label>
            <input type="date" class="form-control form-control-sm" id="filtre-date-fin" name="date_fin"
                   value="<?= htmlspecialchars($filtres['date_fin']) ?>">
        </div>
        <div class="col-md-2">
            <label for="filtre-action" class="form-label small mb-1">Action</label>
            <select class="form-select form-select-sm" id="filtre-action" name="action">
                <option value="">Toutes</option>
                <?php foreach ($actions ?? AuditAction::cases() as $a): ?>
                <option value="<?= $a->value ?>" <?= $filtres['action'] === $a->value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a->label()) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="filtre-utilisateur" class="form-label small mb-1">Utilisateur</label>
            <input type="text" class="form-control form-control-sm" id="filtre-utilisateur" name="utilisateur"
                   value="<?= htmlspecialchars($filtres['utilisateur']) ?>" placeholder="Nom...">
        </div>
        <div class="col-md-2">
            <label for="filtre-ip" class="form-label small mb-1">IP</label>
            <input type="text" class="form-control form-control-sm" id="filtre-ip" name="ip"
                   value="<?= htmlspecialchars($filtres['ip']) ?>" placeholder="Adresse IP...">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filtrer
            </button>
            <?php if ($filtreParams): ?>
            <a href="/admin/audit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<p class="text-muted small mb-2">
    <strong><?= $pagination['total'] ?></strong> entrée(s)
    <?php if ($filtreParams): ?>
        — <a href="/admin/audit" class="text-decoration-none">Voir tout</a>
    <?php endif; ?>
</p>

<!-- Tableau -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 150px;">Date</th>
                        <th>Action</th>
                        <th>Utilisateur</th>
                        <th style="width: 130px;">IP</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                            Aucune entrée trouvée.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log):
                        $action = AuditAction::tryFrom($log['action']);
                        $details = $log['details'] ? json_decode($log['details'], true) : [];
                    ?>
                    <tr>
                        <td style="font-size: 0.82rem; color: var(--text-muted);">
                            <?= htmlspecialchars($log['created_at']) ?>
                        </td>
                        <td>
                            <?php if ($action): ?>
                            <i class="bi <?= $action->icone() ?> me-1"></i>
                            <span style="font-size: 0.88rem;"><?= htmlspecialchars($action->label()) ?></span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size: 0.88rem;"><?= htmlspecialchars($log['action']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.88rem;">
                            <?= htmlspecialchars($log['username'] ?? '-') ?>
                        </td>
                        <td style="font-size: 0.82rem; font-family: monospace;">
                            <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                        </td>
                        <td style="font-size: 0.82rem; max-width: 250px;">
                            <?php if ($details): ?>
                                <?php
                                $resume = [];
                                foreach ($details as $k => $v) {
                                    if (is_string($v)) {
                                        $resume[] = htmlspecialchars($k) . ': ' . htmlspecialchars(mb_substr($v, 0, 60));
                                    }
                                }
                                echo implode(', ', $resume) ?: '<span class="text-muted">-</span>';
                                ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php if ($pagination['page'] > 1): ?>
        <li class="page-item">
            <a class="page-link" href="/admin/audit?page=<?= $pagination['page'] - 1 ?><?= $filtreQueryString ?>">Précédent</a>
        </li>
        <?php endif; ?>

        <?php
        $debut = max(1, $pagination['page'] - 3);
        $fin = min($pagination['totalPages'], $pagination['page'] + 3);
        ?>
        <?php if ($debut > 1): ?>
        <li class="page-item"><a class="page-link" href="/admin/audit?page=1<?= $filtreQueryString ?>">1</a></li>
        <?php if ($debut > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $debut; $p <= $fin; $p++): ?>
        <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
            <a class="page-link" href="/admin/audit?page=<?= $p ?><?= $filtreQueryString ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($fin < $pagination['totalPages']): ?>
        <?php if ($fin < $pagination['totalPages'] - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="/admin/audit?page=<?= $pagination['totalPages'] ?><?= $filtreQueryString ?>"><?= $pagination['totalPages'] ?></a></li>
        <?php endif; ?>

        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
        <li class="page-item">
            <a class="page-link" href="/admin/audit?page=<?= $pagination['page'] + 1 ?><?= $filtreQueryString ?>">Suivant</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
