<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Middleware CORS
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
    $payload = [
        'status'  => true,
        'message' => 'API Slim PHP 4 Running'
    ];
    $response->getBody()->write(json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json');
});

// 1. READ: Ambil Semua Device (Terbungkus / Wrapped)
$app->get('/api/devices', function (Request $request, Response $response) {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM devices ORDER BY id ASC");
        $devices = $stmt->fetchAll();
        
        $payload = [
            'status'  => true,
            'message' => 'Berhasil',
            'data'    => $devices
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
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
            ':name'     => $data['name'],
            ':location' => $data['location'],
            ':status'   => $data['status'] ?? 'OFF'
        ]);

        $payload = [
            'status'  => true,
            'message' => 'Device berhasil ditambahkan'
        ];
        
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
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
        
        $payload = [
            'status'  => true,
            'message' => 'Device berhasil dihapus'
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 4. UPDATE: Edit Data Device
$app->put('/api/devices/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        
        $name     = $data['name'] ?? null;
        $location = $data['location'] ?? null;
        $status   = $data['status'] ?? null;

        $db = getDB();
        $sql = "UPDATE devices SET name = :name, location = :location, status = :status WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':name'     => $name,
            ':location' => $location,
            ':status'   => $status,
            ':id'       => $id
        ]);

        $payload = [
            'status'  => true,
            'message' => 'Device berhasil di-update'
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (\PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 5. Endpoint Login
$app->post('/api/login', function (Request $request, Response $response) {
    try {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && $user['password'] === $password) {
            $payload = [
                'status'  => true,
                'message' => 'Login berhasil',
                'data'    => [
                    'name'  => $user['name'],
                    'email' => $user['email']
                ]
            ];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $payload = [
                'status'  => false,
                'message' => 'Email atau password salah'
            ];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    } catch (PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// 6. Endpoint Register
$app->post('/api/register', function (Request $request, Response $response) {
    try {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $payload = [
                'status'  => false,
                'message' => 'Semua kolom wajib diisi'
            ];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = getDB();
        
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            $payload = [
                'status'  => false,
                'message' => 'Email sudah terdaftar'
            ];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':password' => $password
        ]);

        $payload = [
            'status'  => true,
            'message' => 'Registrasi akun berhasil'
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $payload = [
            'status'  => false,
            'message' => $e->getMessage()
        ];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->run();