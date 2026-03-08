<?php

namespace Platform\Controller;

class LegalController
{
    public function politiqueConfidentialite(): void
    {
        require __DIR__ . '/../../templates/politique-de-confidentialite.php';
    }

    public function mentionsLegales(): void
    {
        require __DIR__ . '/../../templates/mentions-legales.php';
    }
}
