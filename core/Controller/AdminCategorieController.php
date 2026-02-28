<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\User\AccessControl;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminCategorieController
{
    public function index(): void
    {
        Response::redirect('/admin/plugins?onglet=categories');
    }

    public function formulaireCreation(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();

        Layout::render('layout', [
            'template'          => 'admin/categorie-form',
            'pageTitle'         => 'Nouvelle catégorie',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'plugins',
        ]);
    }

    public function creer(Request $req): void
    {
        $nom = trim($req->post('nom', ''));
        $icone = trim($req->post('icone', 'bi-folder'));
        $sortOrder = (int) $req->post('sort_order', 100);

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['nom' => $nom],
            ['nom' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/categories/creer');
        }

        $db = Connection::get();

        // Vérifier unicité du nom
        $stmt = $db->prepare('SELECT id FROM categories WHERE nom = ?');
        $stmt->execute([$nom]);
        if ($stmt->fetch()) {
            Flash::error('Une catégorie avec ce nom existe déjà.');
            Response::redirect('/admin/categories/creer');
        }

        $stmt = $db->prepare(
            'INSERT INTO categories (nom, icone, sort_order) VALUES (?, ?, ?)'
        );
        $stmt->execute([$nom, $icone, $sortOrder]);

        Flash::success("Catégorie « {$nom} » créée.");
        Response::redirect('/admin/plugins?onglet=categories');
    }

    public function formulaireEdition(Request $req, array $params): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([(int) $params['id']]);
        $categorie = $stmt->fetch();

        if (!$categorie) {
            Response::abort(404);
        }

        Layout::render('layout', [
            'template'          => 'admin/categorie-form',
            'pageTitle'         => 'Modifier ' . $categorie['nom'],
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'plugins',
            'categorie'         => $categorie,
        ]);
    }

    public function mettreAJour(Request $req, array $params): void
    {
        $db = Connection::get();
        $id = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $categorie = $stmt->fetch();

        if (!$categorie) {
            Response::abort(404);
        }

        $nom = trim($req->post('nom', ''));
        $icone = trim($req->post('icone', 'bi-folder'));
        $sortOrder = (int) $req->post('sort_order', 100);

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['nom' => $nom],
            ['nom' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/categories/' . $id . '/editer');
        }

        // Vérifier unicité du nom (hors lui-même)
        $stmt = $db->prepare('SELECT id FROM categories WHERE nom = ? AND id != ?');
        $stmt->execute([$nom, $id]);
        if ($stmt->fetch()) {
            Flash::error('Une catégorie avec ce nom existe déjà.');
            Response::redirect('/admin/categories/' . $id . '/editer');
        }

        $stmt = $db->prepare(
            'UPDATE categories SET nom = ?, icone = ?, sort_order = ? WHERE id = ?'
        );
        $stmt->execute([$nom, $icone, $sortOrder, $id]);

        Flash::success('Catégorie mise à jour.');
        Response::redirect('/admin/plugins?onglet=categories');
    }

    public function supprimer(Request $req, array $params): void
    {
        $db = Connection::get();
        $id = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $categorie = $stmt->fetch();

        if (!$categorie) {
            Flash::error('Catégorie introuvable.');
            Response::redirect('/admin/plugins?onglet=categories');
        }

        // ON DELETE SET NULL gère le reset des plugins, mais on peut aussi le faire explicitement
        $db->prepare('UPDATE modules SET categorie_id = NULL WHERE categorie_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);

        Flash::success("Catégorie « {$categorie['nom']} » supprimée. Les plugins associés sont désormais non classés.");
        Response::redirect('/admin/plugins?onglet=categories');
    }

    /**
     * POST /admin/categories/reordonner — AJAX : réordonne les catégories par drag-and-drop.
     */
    public function reordonner(Request $req): void
    {
        $ordres = $req->post('ordres') ?? [];

        if (!is_array($ordres) || $ordres === []) {
            Response::json(['ok' => false, 'erreur' => 'Données manquantes.']);
        }

        $db = Connection::get();
        $stmt = $db->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');

        foreach ($ordres as $position => $id) {
            $stmt->execute([(int) $position * 10 + 10, (int) $id]);
        }

        Response::json(['ok' => true]);
    }
}
