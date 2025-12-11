<?php
// app/Services/ProfileService.php
// -------------------------------------------------------------
// Lógica de perfil del usuario:
// - Lee datos de user_details (EPA, nombre, etc.)
// - Lee datos del contractor (compañía, CAC, dirección)
// - Calcula flags "verificado" usando el esquema real
// -------------------------------------------------------------
declare(strict_types=1);

class ProfileService
{
    public function __construct(
        private UserDetailsRepository $user_details_repo,
        private ContractorRepository $contractor_repo
    ) {}

    /**
     * @param array<string,mixed> $session_user
     * @return array{
     *   session_user: array<string,mixed>,
     *   user_details: ?array<string,mixed>,
     *   contractor: ?array<string,mixed>,
     *   verification: array<string,mixed>
     * }
     */
    public function get_profile_data(array $session_user): array
    {
        $user_id       = (string)($session_user['user_id'] ?? '');
        $user_type     = strtoupper((string)($session_user['user_type'] ?? ''));
        $contractor_id = $session_user['contractor_id'] ?? null;

        // --- user_details (schema real) ---
        $user_details = null;
        if ($user_id !== '') {
            $user_details = $this->user_details_repo->find_by_user_id($user_id);
        }

        // --- contractors (schema real) ---
        $contractor = null;
        if (!empty($contractor_id)) {
            $contractor = $this->contractor_repo->find_by_id((int)$contractor_id);
        }

        // ===============================
        //  Cálculo de verificación
        // ===============================

        // 1) ¿Tiene nombre? (first_name + last_name en user_details)
        $has_name = false;
        if ($user_details) {
            $fn = trim((string)($user_details['first_name'] ?? ''));
            $ln = trim((string)($user_details['last_name'] ?? ''));
            $has_name = ($fn !== '' && $ln !== '');
        }

        // 2) EPA: número + foto
        $has_epa_number = false;
        $has_epa_photo  = false;
        if ($user_details) {
            $has_epa_number = (trim((string)($user_details['epa_certification_number'] ?? '')) !== '');
            $has_epa_photo  = (trim((string)($user_details['epa_photo_url'] ?? '')) !== '');
        }

        // Para usuarios CON / TEC el EPA es obligatorio (ya lo exige el trigger),
        // pero lo usamos igual para mostrar estado.
        $is_epa_required = in_array($user_type, ['CON','TEC'], true);
        $is_epa_ok       = !$is_epa_required || $has_epa_number;

        // 3) Contractor / CAC: usando contrato real
        $has_contractor   = (bool)$contractor;
        $has_cac_license  = false;
        $is_contractor_active = false;
        $is_cac_verified  = false;

        if ($contractor) {
            $has_cac_license     = trim((string)$contractor['cac_license_number']) !== '';
            $is_contractor_active = (int)$contractor['is_active'] === 1;
            // Tomamos "CAC verificado" como contractor activo (aprobado)
            $is_cac_verified      = $has_cac_license && $is_contractor_active;
        }

        // 4) Perfil verificado global:
        //    - tiene user_details
        //    - tiene nombre
        //    - si EPA es requerido, tiene EPA number
        $is_profile_verified = (bool)($user_details && $has_name && $is_epa_ok);

        return [
            'session_user' => $session_user,
            'user_details' => $user_details,
            'contractor'   => $contractor,
            'verification' => [
                'has_name'            => $has_name,
                'has_epa_number'      => $has_epa_number,
                'has_epa_photo'       => $has_epa_photo,
                'is_epa_required'     => $is_epa_required,
                'is_epa_ok'           => $is_epa_ok,
                'has_contractor'      => $has_contractor,
                'has_cac_license'     => $has_cac_license,
                'is_contractor_active'=> $is_contractor_active,
                'is_cac_verified'     => $is_cac_verified,
                'is_profile_verified' => $is_profile_verified,
            ],
        ];
    }
}
