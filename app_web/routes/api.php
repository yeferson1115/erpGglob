<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\OperacionController;
use App\Http\Controllers\API\FinanzaController;
use App\Http\Controllers\API\ContactosController;
use \App\Http\Controllers\API\EventoController;
use App\Http\Controllers\API\PropiedadController;
use App\Http\Controllers\API\CursoController;
use App\Http\Controllers\API\IngresoController;
use App\Http\Controllers\API\IngresoImageController;
use App\Http\Controllers\API\AvaluoController;
use App\Http\Controllers\API\InspeccionController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/propiedades/search', [PropiedadController::class, 'search']);
// Rutas protegidas
Route::middleware(['auth:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'me']);

    // Solo para usuarios con rol "admin"
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin-only', fn () => response()->json(['message' => 'Bienvenido administrador']));
    });

    // Solo para usuarios con permiso "view reports"
    Route::middleware('permission:view reports')->group(function () {
        Route::get('/reports', fn () => response()->json(['message' => 'Aquí están tus reportes']));
    });
    

    Route::get('/roles/datatables', [RoleController::class, 'index']);

    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{id}', [RoleController::class, 'show']);
    Route::put('roles/{id}', [RoleController::class, 'update']);
    Route::delete('roles/{id}', [RoleController::class, 'destroy']);
    Route::get('permissions', [RoleController::class, 'allPermissions']);
    Route::apiResource('users', UserController::class); 
    Route::get('/getroles', [RoleController::class, 'getroles']);

    Route::apiResource('operaciones', OperacionController::class);
    Route::apiResource('finanzas', FinanzaController::class);
    Route::apiResource('contactos', ContactosController::class);
    Route::apiResource('eventos', EventoController::class);
    Route::apiResource('ingreso', IngresoController::class);
    Route::apiResource('avaluo', AvaluoController::class);
    Route::apiResource('inspeccion', InspeccionController::class);
    
    

    Route::prefix('propiedades')->group(function () {
        Route::get('/', [PropiedadController::class, 'index']);
        Route::post('/', [PropiedadController::class, 'store']);
        Route::get('{id}', [PropiedadController::class, 'show']);
        Route::put('{id}', [PropiedadController::class, 'update']);
        Route::delete('{id}', [PropiedadController::class, 'destroy']);
    });
    Route::apiResource('cursos', CursoController::class);

   
    Route::get('cursos/{id}/detalle', [CursoController::class, 'showWithProgreso']);
    Route::post('lecciones/{id}/completar', [CursoController::class, 'completarLeccion']);
    Route::post('lecciones/{id}/responder', [CursoController::class, 'responderExamen']);
    Route::post('cursos/{id}/completar', [CursoController::class, 'marcarCursoCompletado']);
    Route::prefix('ingresos-imagenes/{avaluoId}')->group(function () {
        Route::get('/imagenes', [IngresoImageController::class, 'index']);
        Route::post('/imagenes', [IngresoImageController::class, 'store']);
        Route::post('/imagenes/delete', [IngresoImageController::class, 'delete']);
    });
    Route::post('ingreso/import', [IngresoController::class, 'import']);
  
});

Route::middleware('auth:api')->post('/refresh', [AuthController::class, 'refresh']);


