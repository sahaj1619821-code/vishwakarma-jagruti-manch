<?php
/*
File: admin/surveyor-permission.php

Purpose:
- Village Surveyor ko allotted village/city ka hi data dikhana.
- Section permission ke hisab se admin menu/page access dena.

Required tables:
1) surveyor_villages
2) user_section_permissions
*/

if (!function_exists('sp_admin_id')) {
    function sp_admin_id() {
        return intval(
            $_SESSION['admin_id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['id']
            ?? $_SESSION['admin_user_id']
            ?? 0
        );
    }
}

if (!function_exists('sp_admin_role')) {
    function sp_admin_role() {
        return strtolower(trim(
            $_SESSION['admin_role']
            ?? $_SESSION['role']
            ?? $_SESSION['user_role']
            ?? ''
        ));
    }
}

if (!function_exists('sp_table_exists')) {
    function sp_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('sp_column_exists')) {
    function sp_column_exists($conn, $table, $column) {
        if (!sp_table_exists($conn, $table)) {
            return false;
        }

        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('isSuperAdminLike')) {
    function isSuperAdminLike() {
        $role = sp_admin_role();

        return in_array($role, [
            'super_admin',
            'superadmin',
            'admin',
            'administrator',
            'hostel_admin',
            'matrimonial_admin'
        ], true);
    }
}

if (!function_exists('isVillageSurveyor')) {
    function isVillageSurveyor() {
        $role = sp_admin_role();

        return in_array($role, [
            'village_surveyor',
            'village surveyor',
            'surveyor',
            'village-surveyor'
        ], true);
    }
}

if (!function_exists('getSurveyorVillages')) {
    function getSurveyorVillages($conn, $user_id = 0) {
        $villages = [];

        if ($user_id <= 0) {
            $user_id = sp_admin_id();
        }

        $user_id = intval($user_id);

        if ($user_id <= 0 || !sp_table_exists($conn, 'surveyor_villages')) {
            return $villages;
        }

        $q = $conn->query("
            SELECT village_name
            FROM surveyor_villages
            WHERE user_id = '$user_id'
            AND status = 'active'
        ");

        if ($q) {
            while ($row = $q->fetch_assoc()) {
                if (!empty($row['village_name'])) {
                    $villages[] = trim($row['village_name']);
                }
            }
        }

        return array_values(array_unique($villages));
    }
}

if (!function_exists('canViewSection')) {
    function canViewSection($conn, $user_id, $section_key) {
        if (isSuperAdminLike()) {
            return true;
        }

        $user_id = intval($user_id);
        $section_key = $conn->real_escape_string($section_key);

        if ($user_id <= 0 || !sp_table_exists($conn, 'user_section_permissions')) {
            return false;
        }

        $q = $conn->query("
            SELECT id
            FROM user_section_permissions
            WHERE user_id = '$user_id'
            AND section_key = '$section_key'
            AND can_view = 1
            LIMIT 1
        ");

        return ($q && $q->num_rows > 0);
    }
}

if (!function_exists('requireSection')) {
    function requireSection($conn, $section_key) {
        if (!isset($_SESSION)) {
            session_start();
        }

        $user_id = sp_admin_id();

        if ($user_id <= 0) {
            header("Location: login.php");
            exit;
        }

        if (isSuperAdminLike()) {
            return true;
        }

        if (!canViewSection($conn, $user_id, $section_key)) {
            die("Access Denied: Aapko is section ki permission nahi hai.");
        }

        return true;
    }
}

if (!function_exists('surveyVillageSql')) {
    function surveyVillageSql($conn, $column_name, $table_alias = '') {
        if (!isVillageSurveyor()) {
            return '';
        }

        $villages = getSurveyorVillages($conn, sp_admin_id());

        if (empty($villages)) {
            return " AND 1=0 ";
        }

        $safe = [];

        foreach ($villages as $village) {
            $safe[] = "'" . $conn->real_escape_string($village) . "'";
        }

        $prefix = $table_alias != '' ? $table_alias . "." : "";

        return " AND TRIM(LOWER({$prefix}`$column_name`)) IN (" .
            implode(",", array_map(function($v) {
                return "TRIM(LOWER($v))";
            }, $safe)) .
        ") ";
    }
}

if (!function_exists('surveyVillageSqlAnyColumn')) {
    function surveyVillageSqlAnyColumn($conn, $table_name, $columns, $table_alias = '') {
        if (!isVillageSurveyor()) {
            return '';
        }

        $villages = getSurveyorVillages($conn, sp_admin_id());

        if (empty($villages)) {
            return " AND 1=0 ";
        }

        $safe = [];

        foreach ($villages as $village) {
            $safe[] = "'" . $conn->real_escape_string($village) . "'";
        }

        $village_sql = implode(",", array_map(function($v) {
            return "TRIM(LOWER($v))";
        }, $safe));

        $prefix = $table_alias != '' ? $table_alias . "." : "";
        $parts = [];

        foreach ($columns as $column) {
            if (sp_column_exists($conn, $table_name, $column)) {
                $parts[] = "TRIM(LOWER({$prefix}`$column`)) IN ($village_sql)";
            }
        }

        if (empty($parts)) {
            return " AND 1=0 ";
        }

        return " AND (" . implode(" OR ", $parts) . ") ";
    }
}

if (!function_exists('surveyorCanAccessHostel')) {
    function surveyorCanAccessHostel($conn, $hostel_id) {
        if (!isVillageSurveyor()) {
            return true;
        }

        $hostel_id = intval($hostel_id);
        $villages = getSurveyorVillages($conn, sp_admin_id());

        if ($hostel_id <= 0 || empty($villages)) {
            return false;
        }

        $safe = [];
        foreach ($villages as $village) {
            $safe[] = "'" . $conn->real_escape_string($village) . "'";
        }

        $sql = "
            SELECT id
            FROM hostels
            WHERE id = '$hostel_id'
            AND TRIM(LOWER(city)) IN (" .
                implode(",", array_map(function($v) {
                    return "TRIM(LOWER($v))";
                }, $safe)) .
            ")
            LIMIT 1
        ";

        $q = $conn->query($sql);
        return ($q && $q->num_rows > 0);
    }
}

if (!function_exists('surveyorCanAccessBooking')) {
    function surveyorCanAccessBooking($conn, $booking_id) {
        if (!isVillageSurveyor()) {
            return true;
        }

        $booking_id = intval($booking_id);
        $villages = getSurveyorVillages($conn, sp_admin_id());

        if ($booking_id <= 0 || empty($villages)) {
            return false;
        }

        $safe = [];
        foreach ($villages as $village) {
            $safe[] = "'" . $conn->real_escape_string($village) . "'";
        }

        $sql = "
            SELECT id
            FROM hostel_bookings
            WHERE id = '$booking_id'
            AND TRIM(LOWER(city)) IN (" .
                implode(",", array_map(function($v) {
                    return "TRIM(LOWER($v))";
                }, $safe)) .
            ")
            LIMIT 1
        ";

        $q = $conn->query($sql);
        return ($q && $q->num_rows > 0);
    }
}

if (!function_exists('surveyorCanAccessMatrimonial')) {
    function surveyorCanAccessMatrimonial($conn, $profile_id) {
        if (!isVillageSurveyor()) {
            return true;
        }

        $profile_id = intval($profile_id);
        $villages = getSurveyorVillages($conn, sp_admin_id());

        if ($profile_id <= 0 || empty($villages) || !sp_table_exists($conn, 'matrimonial_users')) {
            return false;
        }

        $safe = [];
        foreach ($villages as $village) {
            $safe[] = "'" . $conn->real_escape_string($village) . "'";
        }

        $village_sql = implode(",", array_map(function($v) {
            return "TRIM(LOWER($v))";
        }, $safe));

        $cols = [];
        foreach (['village_rajasthan', 'village', 'village_name', 'city'] as $col) {
            if (sp_column_exists($conn, 'matrimonial_users', $col)) {
                $cols[] = "TRIM(LOWER(`$col`)) IN ($village_sql)";
            }
        }

        if (empty($cols)) {
            return false;
        }

        $q = $conn->query("
            SELECT id
            FROM matrimonial_users
            WHERE id = '$profile_id'
            AND (" . implode(" OR ", $cols) . ")
            LIMIT 1
        ");

        return ($q && $q->num_rows > 0);
    }
}
?>

<?php include 'includes/footer.php'; ?>
