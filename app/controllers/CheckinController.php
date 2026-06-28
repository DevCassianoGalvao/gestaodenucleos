<?php

class CheckinController
{
    private const RAIO_M = 200;

    public function store(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Auth check manual (endpoint JSON — Auth::requireRole redirectaria para HTML)
        if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'professor') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Não autorizado']);
            exit;
        }

        // CSRF manual (verifyCsrf usa die() — não serve para JSON)
        $submitted = $_POST['csrf_token'] ?? '';
        $stored    = $_SESSION['csrf_token'] ?? '';
        if (!$stored || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido. Recarregue a página.']);
            exit;
        }

        // Validar coordenadas
        $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;

        if ($lat === null || $lng === null
            || $lat < -90  || $lat > 90
            || $lng < -180 || $lng > 180
        ) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Coordenadas inválidas.']);
            exit;
        }

        $db      = Database::getInstance();
        $profId  = (int) $_SESSION['user_id'];

        // Buscar núcleo do professor (com coordenadas)
        $stmt = $db->prepare("
            SELECT n.id, n.nome, n.latitude, n.longitude
            FROM nucleo_professores np
            JOIN nucleos n ON n.id = np.nucleo_id
            WHERE np.usuario_id = ?
              AND n.status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$profId]);
        $nucleo = $stmt->fetch();

        if (!$nucleo) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Nenhum núcleo ativo vinculado.']);
            exit;
        }

        // Reverse geocode via Nominatim (OpenStreetMap, gratuito)
        $endereco = $this->reverseGeocode($lat, $lng);

        // Calcular distância se núcleo tem coordenadas
        $distancia = null;
        $status    = 'sem_coordenadas';

        if ($nucleo['latitude'] !== null && $nucleo['longitude'] !== null) {
            $distancia = $this->haversine(
                (float) $nucleo['latitude'],
                (float) $nucleo['longitude'],
                $lat,
                $lng
            );
            $status = $distancia <= self::RAIO_M ? 'dentro_raio' : 'fora_raio';
        }

        // Gravar check-in
        $stmt = $db->prepare("
            INSERT INTO checkins (professor_id, nucleo_id, latitude, longitude, endereco, distancia_m, status, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$profId, (int) $nucleo['id'], $lat, $lng, $endereco, $distancia, $status]);

        // Nome do professor para o e-mail
        $profStmt = $db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
        $profStmt->execute([$profId]);
        $profNome = (string) $profStmt->fetchColumn();

        // Notificar super_admin
        require_once ROOT_PATH . '/app/helpers/Mailer.php';
        Mailer::notifyCheckin($profNome, $nucleo['nome'], $endereco, $lat, $lng, $distancia, $status);

        echo json_encode(['ok' => true, 'status' => $status, 'endereco' => $endereco]);
        exit;
    }

    private function reverseGeocode(float $lat, float $lng): string
    {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?lat=%s&lon=%s&format=json&accept-language=pt-BR',
            urlencode((string) $lat),
            urlencode((string) $lng)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERAGENT      => 'gestao-nucleos/1.0 (+' . APP_URL . ')',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        if (!$resp) {
            return "Lat: {$lat}, Lng: {$lng}";
        }

        $data = json_decode($resp, true);
        return $data['display_name'] ?? "Lat: {$lat}, Lng: {$lng}";
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $R  = 6371000; // raio da Terra em metros
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);
        $a  = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return (int) round($R * $c);
    }
}
