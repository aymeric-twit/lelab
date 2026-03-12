<?php
/** @var string $totpSecret */
/** @var string $totpUri */
/** @var string $qrUrl */
?>

<h2 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Activer l'authentification à deux facteurs</h2>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header">
                <i class="bi bi-qr-code me-1"></i> Configuration 2FA
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <p>Scannez le QR code ci-dessous avec votre application d'authentification
                       (Google Authenticator, Authy, etc.).</p>
                </div>

                <div class="text-center mb-4">
                    <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code TOTP"
                         width="200" height="200" class="border rounded p-2 bg-white">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Clé secrète (saisie manuelle)</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace text-center"
                               value="<?= htmlspecialchars($totpSecret) ?>" readonly id="secretKey">
                        <button class="btn btn-outline-secondary" type="button" onclick="copierSecret()" title="Copier">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <div class="form-text">Si vous ne pouvez pas scanner le QR code, entrez cette clé manuellement dans votre application.</div>
                </div>

                <hr>

                <form method="POST" action="/mon-compte/2fa/confirmer">
                    <?= \Platform\Http\Csrf::field() ?>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold">Code de vérification</label>
                        <input type="text" class="form-control text-center fs-5 fw-bold"
                               id="code" name="code"
                               required autofocus
                               maxlength="6"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               autocomplete="one-time-code"
                               placeholder="000000"
                               style="letter-spacing: 0.5em; max-width: 200px; margin: 0 auto;">
                        <div class="form-text text-center">Entrez le code à 6 chiffres affiché dans votre application pour confirmer l'activation.</div>
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="/mon-compte" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check me-1"></i>Activer la 2FA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function copierSecret() {
    var input = document.getElementById('secretKey');
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = input.nextElementSibling;
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(function() { btn.innerHTML = originalHtml; }, 2000);
    });
}
</script>
