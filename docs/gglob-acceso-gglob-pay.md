# Gglob — Lógica de negocio: Acceso, permisos y módulo Gglob Pay.

## 1) Validación de acceso y permisos

### 1.1 Reglas de acceso para `app_web`

**Objetivo:** permitir el ingreso solo a perfiles de administración/propiedad de negocio con plan habilitado en nube.

#### Reglas obligatorias en Login (`app_web`)
1. El usuario **debe estar autenticado** correctamente (email/contraseña válidos).
2. El usuario **debe tener rol permitido**:
   - `Administrador`
   - `Dueño de Negocio`
3. El usuario **debe pertenecer a una empresa activa**.
4. La empresa debe tener un **plan activo** con la opción **`Gglob Nube` habilitada**.

#### Matriz de decisión de acceso (`app_web`)
- Si rol NO permitido → **Denegar** acceso.
- Si empresa inactiva → **Denegar** acceso.
- Si no tiene plan activo → **Denegar** acceso.
- Si plan activo pero sin `Gglob Nube` → **Denegar** acceso.
- Si cumple todo → **Permitir** acceso y cargar panel principal.

#### Pseudocódigo sugerido
```text
if !auth_ok: deny("Credenciales inválidas")
if role not in [admin, owner]: deny("Rol no permitido")
if company.status != active: deny("Empresa inactiva")
if !company.plan.active: deny("Plan inactivo")
if !company.plan.features.includes("gglob_cloud"): deny("Plan sin Gglob Nube")
allow()
```

---

### 1.2 Menú dinámico en `app_desk`

#### Regla funcional
- Si el plan contratado incluye modalidad **`Multi Caja`**, el menú lateral de `app_desk` debe habilitar el ítem:
  - **Gestión de Cajas**

#### Regla de visibilidad
- `showGestionCajas = plan.features.includes("multi_cash_register")`

#### Comportamiento UX esperado
- Cuando la opción no existe en el plan, el ítem no se muestra (no deshabilitado: oculto).
- Cuando existe, permite administrar:
  - creación/edición de cajas,
  - asignación de cajeros,
  - estado (activa/inactiva).

---

### 1.3 Relación Caja ↔ Usuarios (Cajeros)

#### Regla de dominio
- Una **Caja** puede tener asignado **uno o varios usuarios** (cajeros).
- Un **Cajero** puede operar en una o varias cajas (según política del negocio).

#### Modelo de datos recomendado
- `cash_registers`
- `cash_register_user` (tabla pivote many-to-many)

Campos mínimos sugeridos:
- `cash_registers`: `id`, `company_id`, `name`, `code`, `status`, timestamps.
- `cash_register_user`: `id`, `cash_register_id`, `user_id`, `assigned_by`, `assigned_at`, `is_primary`.

> Restricción: la asignación solo es válida si `cash_register.company_id == user.company_id`.

---

## 2) Arquitectura funcional de Gglob Pay en `app_desk`

## 2.1 Objetivo
Implementar verificación inmediata de transferencias bancarias por QR, registrando origen de pago, banco destino y resultado de validación en tiempo real.

## 2.2 Flujo transaccional (alto nivel)
1. Cajero/Dueño abre módulo **Generación de QR**.
2. Selecciona origen de cobro:
   - `Ahorros`
   - `Wompi - Tarjeta de Crédito`
3. Ingresa monto.
4. Sistema autocompleta:
   - usuario logueado,
   - caja activa/asignada.
5. Sistema crea `payment_intent` y genera payload QR con referencia única.
6. Cliente paga.
7. Motor de verificación consulta/recibe confirmación:
   - Wompi webhook/API,
   - integración Bancolombia (callback o polling seguro).
8. Si la verificación es positiva, se registra en **Verificados**.
9. Reportes consolidan por fecha/cajero/caja.

---

## 2.3 Pestañas internas del módulo

### A) Configuración (Cuentas Destino)
Permite administrar credenciales e integraciones.

**Componentes:**
- Gestión de llaves API de Wompi:
  - `public_key`
  - `private_key` (encriptada)
  - `events_secret`
- Parámetros Bancolombia:
  - endpoint base
  - client_id
  - client_secret (encriptado)
  - certificate metadata (si aplica)
- Cuentas destino por empresa:
  - banco
  - titular
  - número de cuenta
  - tipo (ahorros/corriente)
  - estado

**Reglas:**
- Solo `Administrador` o `Dueño` pueden modificar credenciales.
- `Cajero` solo puede consultar cuentas habilitadas (lectura).

### B) Generación de QR
**Perfil autorizado:** Administrador o Cajero.

**Campos obligatorios:**
- `monto`
- `usuario_logueado_nombre` (autocompletado)
- `caja_asignada` (autovinculada por sesión)
- `origen` (`ahorros` | `wompi_credit_card`)
- `destination_account_id`

**Salida:**
- QR con payload firmado + referencia de transacción.

**Validaciones:**
- monto > 0
- usuario debe tener caja asignada activa
- cuenta destino debe pertenecer a la empresa

### C) Verificados
Panel de consulta con filtros:
- rango de fechas
- cajero

Columnas requeridas:
- nombre del emisor
- número de cuenta origen
- valor verificado
- timestamp del pago
- banco destino
- caja
- cajero
- referencia

### D) Reportes
Generador exportable (CSV/XLSX/PDF) con filtros:
- fecha desde/hasta
- cajero específico
- caja específica (recomendado)

Métricas mínimas:
- total verificado
- cantidad de pagos
- ticket promedio
- detalle por cajero y por caja

---

## 2.4 Estados de una transacción

- `CREATED`: intent creado, QR emitido.
- `PENDING_VERIFICATION`: pago capturado, en validación bancaria.
- `VERIFIED`: validación confirmada.
- `REJECTED`: validación negativa.
- `EXPIRED`: referencia vencida sin confirmación.

Transición recomendada:
`CREATED -> PENDING_VERIFICATION -> VERIFIED|REJECTED|EXPIRED`

---


## 3) Área de usuario final (Cajeros)

Esta es el área más utilizada porque el cajero mantiene el POS abierto durante toda la jornada laboral. Debe ser rápida, simple y operativa para atender clientes en fila, evitando pasos innecesarios.

### 3.1 Principios operativos del módulo de cajeros

- Operación **offline-first**: el cajero puede vender sin internet.
- Persistencia local obligatoria de ventas y eventos operativos.
- Sincronización automática cuando regrese la conexión.
- Permisos heredados desde Dueño/Administrador con control granular.

Permisos configurables por el dueño (ejemplos):
- permitir descuentos,
- permitir devoluciones,
- permitir salidas de dinero,
- permitir anulación de ticket,
- permitir reapertura de ticket cerrado.

> Regla de negocio: el cajero no puede ejecutar funciones fuera de sus permisos asignados.

### 3.2 Operación de turno con control biométrico (entrada/salida)

Debe existir en el menú principal la pestaña **Turnos**, con dos acciones: **Apertura de turno** y **Cierre de turno**.

#### A) Apertura de turno

Flujo mínimo:
1. El trabajador selecciona **Apertura de turno**.
2. El sistema valida identidad por biometría en vivo:
   - foto en vivo desde cámara (no se permite cargar archivo), o
   - huella dactilar con lector biométrico.
3. Según configuración del negocio, solicita **Fondo de Caja** (opcional u obligatorio).
4. Se confirma apertura y se habilita operación POS.

Registro automático obligatorio:
- fecha y hora de apertura,
- cajero,
- caja (Caja 1, Caja 2, etc.),
- sucursal o punto de venta,
- evidencia biométrica (foto/huella),
- monto de fondo de caja (si aplica).

#### B) Cierre de turno

Flujo mínimo:
1. El trabajador selecciona **Cerrar turno**.
2. El sistema vuelve a validar identidad por biometría en vivo (foto/huella).
3. Según reglas del negocio, solicita:
   - generar corte de cajero,
   - confirmar efectivo contado (opcional).
4. El sistema calcula diferencia entre efectivo esperado y efectivo contado cuando aplique.

Registro automático obligatorio:
- fecha y hora de cierre,
- totales vendidos del turno,
- ventas por tipo de pago (efectivo, transferencia, tarjeta, cheque),
- devoluciones,
- entradas y salidas de efectivo,
- diferencia o descuadre.

**Control administrativo:** aperturas y cierres deben listarse en el panel del administrador para auditoría de horarios, identidad y desempeño por turno.

### 3.3 Registro de ventas (operación POS diaria)

Debe existir una pestaña principal **Ventas** como pantalla de trabajo continuo del cajero.

#### A) Definición de Ticket
Un **Ticket** es la venta en proceso (carrito). Debe permitir múltiples tickets simultáneos (activo + pendientes).

#### B) Formas de agregar productos
1. **Lector de código de barras** (principal): lectura y adición automática.
2. **Búsqueda rápida** por:
   - nombre,
   - código interno,
   - código de barras.
3. **Selección por categorías**:
   - categoría,
   - producto.

> Esta modalidad es clave para negocios con productos sin código (cafeterías, comidas rápidas, panadería, etc.).

#### C) Operaciones dentro del ticket
El cajero debe poder:
- aumentar/disminuir cantidad,
- eliminar producto,
- ver precio antes de vender (verificador),
- aplicar promociones o descuentos (solo si está permitido por rol).

### 3.4 Cobro y selección del tipo de pago

El POS debe incluir acción de **Cobrar** que abra la pantalla de pago con métodos habilitados por configuración del negocio:
- transferencia,
- tarjeta,
- efectivo,
- cheque.

Reglas:
- El dueño define métodos disponibles.
- El cajero solo puede usar métodos previamente habilitados.
- Soporte de **pago mixto** (recomendado): combinación de dos o más medios en una venta.

#### Efectivo y cálculo de cambio
Cuando el medio de pago incluya efectivo:
1. el cajero digita monto recibido,
2. el sistema calcula y muestra cambio automáticamente,
3. el cambio queda registrado en el comprobante y en auditoría del movimiento.


## 4) Requerimiento técnico de datos (auditoría)

## 4.1 Vinculación estricta por Caja y Cajero
Toda transacción en Gglob Pay debe registrar de forma obligatoria:
- `company_id`
- `cash_register_id` (**NO NULL**)
- `cashier_user_id` (**NO NULL**)
- `created_by_user_id` (si difiere del cajero)

## 4.2 Esquema recomendado de `gglob_pay_payments`
Campos sugeridos (además de los actuales):
- `payment_intent_id` (UUID)
- `reference_code` (único por empresa)
- `source_channel` (`ahorros`, `wompi_credit_card`)
- `destination_account_id`
- `destination_bank`
- `sender_name`
- `source_account_number`
- `amount`
- `currency` (COP)
- `status`
- `verified_at`
- `verification_provider` (`wompi`, `bancolombia`)
- `verification_trace`
- `cash_register_id`
- `cashier_user_id`
- `company_id`
- `metadata` JSON

## 4.3 Restricciones e integridad
- FK `cash_register_id -> cash_registers.id`
- FK `cashier_user_id -> users.id`
- FK `company_id -> companies.id`
- CHECK: `amount > 0`
- Índices:
  - `(company_id, verified_at)`
  - `(company_id, cashier_user_id, verified_at)`
  - `(company_id, cash_register_id, verified_at)`
  - `reference_code` único por empresa

## 3.4 Reglas de consistencia en backend
Antes de guardar pago verificado:
1. Validar que `cashier_user_id` pertenezca a la empresa.
2. Validar que `cash_register_id` pertenezca a la empresa.
3. Validar relación cajero-caja activa en pivote `cash_register_user`.
4. Si falla cualquier validación, rechazar persistencia (HTTP 422/409).

---

## 4) API mínima sugerida

### Acceso y contexto
- `POST /api/auth/login` → retorna usuario + roles + features de plan.
- `GET /api/profile` → incluye `business_role`, `company`, `plan.features`, `cash_registers` asignadas.

### Cajas
- `GET /api/cash-registers`
- `POST /api/cash-registers`
- `POST /api/cash-registers/{id}/assign-user`

### Gglob Pay
- `GET /api/gglob-pay/destination-accounts`
- `POST /api/gglob-pay/destination-accounts`
- `POST /api/gglob-pay/qr/intents`
- `POST /api/gglob-pay/webhooks/wompi`
- `POST /api/gglob-pay/webhooks/bancolombia`
- `GET /api/gglob-pay/payments?from=&to=&cashier=&cash_register=`
- `GET /api/gglob-pay/reports?from=&to=&cashier=&cash_register=&format=csv`

---

## 5) Criterios de aceptación

1. Un usuario con rol distinto de Administrador o Dueño **no puede entrar a `app_web`**.
2. Un usuario sin plan activo con `Gglob Nube` **no puede entrar a `app_web`**.
3. Si el plan incluye `Multi Caja`, aparece **Gestión de Cajas** en `app_desk`.
4. Un QR generado registra siempre monto, usuario logueado y caja asignada.
5. El panel Verificados filtra por fecha/cajero y muestra los campos requeridos.
6. Los reportes se pueden exportar por rango de fechas y cajero.
7. Ningún pago puede persistirse sin `cash_register_id` y `cashier_user_id` válidos.

