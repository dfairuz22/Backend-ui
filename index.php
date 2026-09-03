<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
require __DIR__ . '/vendor/autoload.php';
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Koneksi Database PDO
function getDB() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'iot_db';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'API Slim PHP 4 Running']));
    return $response->withHeader('Content-Type', 'application/json');
});

// 1. READ: Ambil Semua Device (Diurutkan ASC dari ID terkecil ke terbesar)
$app->get('/api/devices', function (Request $request, Response $response) {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM devices ORDER BY id ASC");
        $devices = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode($devices));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 2. CREATE: Tambah Device Baru
$app->post('/api/devices', function (Request $request, Response $response) {
    try {
        $data = $request->getParsedBody();
        $db = getDB();
        
        $stmt = $db->prepare("INSERT INTO devices (name, location, status) VALUES (:name, :location, :status)");
        $stmt->execute([
            ':name' => $data['name'],
            ':location' => $data['location'],
            ':status' => $data['status'] ?? 'OFF'
        ]);
        
        $response->getBody()->write(json_encode(['message' => 'Device berhasil ditambahkan']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 3. DELETE: Hapus Device Berdasarkan ID
$app->delete('/api/devices/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $db = getDB();
        
        $stmt = $db->prepare("DELETE FROM devices WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $response->getBody()->write(json_encode(['message' => 'Device berhasil dihapus']));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 4. Endpoint untuk Update Data Device berdasarkan ID
$app->put('/api/devices/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        
        $name = $data['name'] ?? null;
        $location = $data['location'] ?? null;
        $status = $data['status'] ?? null;

        // PERBAIKAN DI SINI: Menggunakan fungsi getDB() agar sama dengan endpoint lain
        $db = getDB();
        
        $sql = "UPDATE devices SET name = :name, location = :location, status = :status WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':location' => $location,
            ':status' => $status,
            ':id' => $id
        ]);

        $payload = json_encode(["message" => "Device berhasil di-update"]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (\PDOException $e) {
        $error = json_encode(["error" => $e->getMessage()]);
        $response->getBody()->write($error);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->run();