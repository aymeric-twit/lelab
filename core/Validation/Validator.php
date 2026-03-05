<?php

namespace Platform\Validation;

class Validator
{
    /** @var array<string, string[]> */
    private array $erreurs = [];

    /** @var array<string, mixed> Données complètes pour les règles cross-champ (confirme) */
    private array $donnees = [];

    /**
     * @param array<string, mixed> $donnees
     * @param array<string, string> $regles  Ex: ['username' => 'requis|min:3|max:50', 'email' => 'email']
     */
    public function valider(array $donnees, array $regles): bool
    {
        $this->erreurs = [];
        $this->donnees = $donnees;

        foreach ($regles as $champ => $regleStr) {
            $valeur = $donnees[$champ] ?? null;
            $listeRegles = explode('|', $regleStr);

            foreach ($listeRegles as $regle) {
                $this->appliquerRegle($champ, $valeur, $regle);
            }
        }

        return $this->erreurs === [];
    }

    /**
     * @return array<string, string[]>
     */
    public function erreurs(): array
    {
        return $this->erreurs;
    }

    public function premiereErreur(): ?string
    {
        foreach ($this->erreurs as $champErreurs) {
            if ($champErreurs !== []) {
                return $champErreurs[0];
            }
        }
        return null;
    }

    private function appliquerRegle(string $champ, mixed $valeur, string $regle): void
    {
        $params = [];
        if (str_contains($regle, ':')) {
            [$regle, $paramStr] = explode(':', $regle, 2);
            $params = explode(',', $paramStr);
        }

        match ($regle) {
            'requis' => $this->validerRequis($champ, $valeur),
            'email' => $this->validerEmail($champ, $valeur),
            'min' => $this->validerMin($champ, $valeur, (int) $params[0]),
            'max' => $this->validerMax($champ, $valeur, (int) $params[0]),
            'mot_de_passe' => $this->validerMotDePasse($champ, $valeur),
            'in' => $this->validerIn($champ, $valeur, $params),
            'chemin' => $this->validerChemin($champ, $valeur),
            'slug' => $this->validerSlug($champ, $valeur),
            'confirme' => $this->validerConfirme($champ, $valeur),
            'unique' => $this->validerUnique($champ, $valeur, $params[0] ?? '', $params[1] ?? ''),
            default => null,
        };
    }

    private function ajouterErreur(string $champ, string $message): void
    {
        $this->erreurs[$champ][] = $message;
    }

    private function validerRequis(string $champ, mixed $valeur): void
    {
        if ($valeur === null || (is_string($valeur) && trim($valeur) === '')) {
            $this->ajouterErreur($champ, "Le champ {$champ} est requis.");
        }
    }

    private function validerEmail(string $champ, mixed $valeur): void
    {
        if ($valeur !== null && $valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
            $this->ajouterErreur($champ, "Le champ {$champ} doit être une adresse email valide.");
        }
    }

    private function validerMin(string $champ, mixed $valeur, int $min): void
    {
        if (is_string($valeur) && mb_strlen($valeur) < $min) {
            $this->ajouterErreur($champ, "Le champ {$champ} doit contenir au moins {$min} caractères.");
        }
    }

    private function validerMax(string $champ, mixed $valeur, int $max): void
    {
        if (is_string($valeur) && mb_strlen($valeur) > $max) {
            $this->ajouterErreur($champ, "Le champ {$champ} ne doit pas dépasser {$max} caractères.");
        }
    }

    /**
     * Mot de passe : au moins 8 caractères, une majuscule, une minuscule, un chiffre.
     */
    private function validerMotDePasse(string $champ, mixed $valeur): void
    {
        if (!is_string($valeur) || $valeur === '') {
            return;
        }
        if (mb_strlen($valeur) < 8) {
            $this->ajouterErreur($champ, "Le mot de passe doit contenir au moins 8 caractères.");
        }
        if (!preg_match('/[A-Z]/', $valeur)) {
            $this->ajouterErreur($champ, "Le mot de passe doit contenir au moins une majuscule.");
        }
        if (!preg_match('/[a-z]/', $valeur)) {
            $this->ajouterErreur($champ, "Le mot de passe doit contenir au moins une minuscule.");
        }
        if (!preg_match('/\d/', $valeur)) {
            $this->ajouterErreur($champ, "Le mot de passe doit contenir au moins un chiffre.");
        }
    }

    private function validerIn(string $champ, mixed $valeur, array $valeursPossibles): void
    {
        if ($valeur !== null && $valeur !== '' && !in_array($valeur, $valeursPossibles, true)) {
            $options = implode(', ', $valeursPossibles);
            $this->ajouterErreur($champ, "Le champ {$champ} doit être parmi : {$options}.");
        }
    }

    private function validerChemin(string $champ, mixed $valeur): void
    {
        if ($valeur !== null && $valeur !== '' && !is_dir($valeur)) {
            $this->ajouterErreur($champ, "Le chemin spécifié n'existe pas ou n'est pas un répertoire.");
        }
    }

    private function validerSlug(string $champ, mixed $valeur): void
    {
        if ($valeur !== null && $valeur !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$/', $valeur)) {
            $this->ajouterErreur($champ, "Le slug doit contenir uniquement des lettres minuscules, chiffres et tirets (2-50 caractères).");
        }
    }

    /**
     * Vérifie que {champ}_confirmation correspond à {champ}.
     */
    private function validerConfirme(string $champ, mixed $valeur): void
    {
        $confirmation = $this->donnees[$champ . '_confirmation'] ?? null;
        if ($valeur !== null && $valeur !== '' && $valeur !== $confirmation) {
            $this->ajouterErreur($champ, "La confirmation du champ {$champ} ne correspond pas.");
        }
    }

    /**
     * Vérifie l'unicité dans la base de données.
     * Usage : 'unique:table,colonne'
     */
    private function validerUnique(string $champ, mixed $valeur, string $table, string $colonne): void
    {
        if ($valeur === null || $valeur === '' || $table === '' || $colonne === '') {
            return;
        }

        $tableAutorisees = ['users'];
        if (!in_array($table, $tableAutorisees, true)) {
            return;
        }

        $colonneAutorisees = ['username', 'email'];
        if (!in_array($colonne, $colonneAutorisees, true)) {
            return;
        }

        $db = \Platform\Database\Connection::get();
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$colonne} = ? AND deleted_at IS NULL");
        $stmt->execute([$valeur]);

        if ((int) $stmt->fetchColumn() > 0) {
            $this->ajouterErreur($champ, "Cette valeur est déjà utilisée.");
        }
    }
}
