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
use PDO;

class AdminGroupController
{
    public function index(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $groupes = $db->query(
            'SELECT ug.*,
                    (SELECT COUNT(*) FROM user_group_members ugm WHERE ugm.group_id = ug.id) AS nb_membres,
                    (SELECT COUNT(*) FROM group_module_access gma WHERE gma.group_id = ug.id AND gma.granted = 1) AS nb_modules
             FROM user_groups ug
             ORDER BY ug.name'
        )->fetchAll();

        Layout::render('layout', [
            'template'          => 'admin/groups',
            'pageTitle'         => 'Groupes',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'groups',
            'groupes'           => $groupes,
        ]);
    }

    public function formulaireCreation(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
        $utilisateurs = $db->query('SELECT id, username, role FROM users WHERE deleted_at IS NULL ORDER BY username')->fetchAll();

        Layout::render('layout', [
            'template'          => 'admin/group-form',
            'pageTitle'         => 'Nouveau groupe',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'groups',
            'modules'           => $modules,
            'utilisateurs'      => $utilisateurs,
        ]);
    }

    public function creer(Request $req): void
    {
        $nom = trim($req->post('name', ''));
        $description = trim($req->post('description', ''));

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['name' => $nom],
            ['name' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/groups/create');
        }

        $db = Connection::get();

        // Vérifier unicité du nom
        $stmt = $db->prepare('SELECT id FROM user_groups WHERE name = ?');
        $stmt->execute([$nom]);
        if ($stmt->fetch()) {
            Flash::error('Un groupe avec ce nom existe déjà.');
            Response::redirect('/admin/groups/create');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO user_groups (name, description) VALUES (?, ?)'
            );
            $stmt->execute([$nom, $description !== '' ? $description : null]);
            $groupeId = (int) $db->lastInsertId();

            $this->sauvegarderMembresEtAcces($req, $db, $groupeId);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Flash::error('Erreur lors de la création du groupe : ' . $e->getMessage());
            Response::redirect('/admin/groups/create');
        }

        Flash::success("Groupe « {$nom} » créé.");
        Response::redirect('/admin/groups');
    }

    public function formulaireEdition(Request $req, array $params): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $stmt = $db->prepare('SELECT * FROM user_groups WHERE id = ?');
        $stmt->execute([(int) $params['id']]);
        $groupe = $stmt->fetch();

        if (!$groupe) {
            Response::abort(404);
        }

        $groupeId = (int) $groupe['id'];
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
        $utilisateurs = $db->query('SELECT id, username, role FROM users WHERE deleted_at IS NULL ORDER BY username')->fetchAll();

        // Membres actuels du groupe
        $stmtMembres = $db->prepare('SELECT user_id FROM user_group_members WHERE group_id = ?');
        $stmtMembres->execute([$groupeId]);
        $membresIds = $stmtMembres->fetchAll(PDO::FETCH_COLUMN);

        // Accès modules actuels du groupe
        $stmtAcces = $db->prepare('SELECT module_id FROM group_module_access WHERE group_id = ? AND granted = 1');
        $stmtAcces->execute([$groupeId]);
        $accesModuleIds = $stmtAcces->fetchAll(PDO::FETCH_COLUMN);

        Layout::render('layout', [
            'template'          => 'admin/group-form',
            'pageTitle'         => 'Modifier ' . $groupe['name'],
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'groups',
            'groupe'            => $groupe,
            'modules'           => $modules,
            'utilisateurs'      => $utilisateurs,
            'membresIds'        => $membresIds,
            'accesModuleIds'    => $accesModuleIds,
        ]);
    }

    public function mettreAJour(Request $req, array $params): void
    {
        $db = Connection::get();
        $id = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $groupe = $stmt->fetch();

        if (!$groupe) {
            Response::abort(404);
        }

        $nom = trim($req->post('name', ''));
        $description = trim($req->post('description', ''));

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['name' => $nom],
            ['name' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/groups/' . $id . '/edit');
        }

        // Vérifier unicité du nom (hors lui-même)
        $stmt = $db->prepare('SELECT id FROM user_groups WHERE name = ? AND id != ?');
        $stmt->execute([$nom, $id]);
        if ($stmt->fetch()) {
            Flash::error('Un groupe avec ce nom existe déjà.');
            Response::redirect('/admin/groups/' . $id . '/edit');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE user_groups SET name = ?, description = ? WHERE id = ?'
            );
            $stmt->execute([$nom, $description !== '' ? $description : null, $id]);

            $this->sauvegarderMembresEtAcces($req, $db, $id);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Flash::error('Erreur lors de la mise à jour du groupe.');
            Response::redirect('/admin/groups/' . $id . '/edit');
        }

        Flash::success('Groupe mis à jour.');
        Response::redirect('/admin/groups');
    }

    public function supprimer(Request $req, array $params): void
    {
        $db = Connection::get();
        $id = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $groupe = $stmt->fetch();

        if (!$groupe) {
            Flash::error('Groupe introuvable.');
            Response::redirect('/admin/groups');
        }

        // ON DELETE CASCADE supprime les membres et accès automatiquement
        $db->prepare('DELETE FROM user_groups WHERE id = ?')->execute([$id]);

        Flash::success("Groupe « {$groupe['name']} » supprimé.");
        Response::redirect('/admin/groups');
    }

    private function sauvegarderMembresEtAcces(Request $req, PDO $db, int $groupeId): void
    {
        // Sauvegarder les membres
        $db->prepare('DELETE FROM user_group_members WHERE group_id = ?')->execute([$groupeId]);
        $membresIds = $req->post('membres') ?? [];
        if (is_array($membresIds) && $membresIds !== []) {
            $stmtMembre = $db->prepare(
                'INSERT INTO user_group_members (group_id, user_id) VALUES (?, ?)'
            );
            foreach ($membresIds as $userId) {
                $stmtMembre->execute([$groupeId, (int) $userId]);
            }
        }

        // Sauvegarder les accès modules
        $db->prepare('DELETE FROM group_module_access WHERE group_id = ?')->execute([$groupeId]);
        $accesModules = $req->post('modules_access') ?? [];
        if (is_array($accesModules) && $accesModules !== []) {
            $stmtAcces = $db->prepare(
                'INSERT INTO group_module_access (group_id, module_id, granted) VALUES (?, ?, 1)'
            );
            foreach ($accesModules as $moduleId) {
                $stmtAcces->execute([$groupeId, (int) $moduleId]);
            }
        }
    }
}
