# Plan paso a paso: Solicitud de crédito + autorización de descuento

Este documento define una implementación incremental para digitalizar el proceso completo de:

1. Solicitud de estudio de crédito.
2. Autorización de descuento.
3. Carga documental y validaciones.
4. Firma digital.
5. Generación de PDF con formato similar al diseño compartido.
6. Flujo administrativo y notificaciones.

---

## 1) Alcance funcional (MVP)

### Flujo cliente
1. El cliente inicia una solicitud.
2. Diligencia datos personales, laborales y del crédito en un único formulario.
3. Verifica su celular con código OTP por SMS.
4. Adjunta documentos obligatorios:
   - Cédula frente.
   - Cédula reverso.
   - Foto sosteniendo el documento.
5. Firma digitalmente en pantalla.
6. Revisa resumen, consulta valor de cuota, y envía la solicitud.
7. El sistema genera PDF y envía notificación por correo a administradores.

### Flujo administrador
1. Ingreso con usuario interno (hasta 5 usuarios).
2. Visualiza listado de solicitudes con filtros.
3. Consulta detalle, documentos adjuntos y PDF generado.
4. Cambia estado: `Aprobado` / `No Aprobado`.
5. Gestiona catálogo de empresas (CRUD).
6. Verifica pagos (Wompi) en módulo de conciliación.

---

## 2) Campos del formulario (según formato compartido)

> Estos campos son la base para pantalla y PDF. Se pueden ampliar después sin romper el modelo.

### Sección: Solicitud de crédito
- Fecha de solicitud.

### Sección: Datos personales
- Nombres y apellidos.
- Documento de identidad:
  - Tipo.
  - Número.
  - Fecha de expedición.
- Teléfonos de contacto:
  - Celular 1.
  - Celular 2.
- Correo electrónico.
- Dirección de residencia.
- Barrio.
- Ciudad.

### Sección: Datos laborales
- Empresa donde labora.
- Sede.
- Fecha de ingreso.
- Tipo de contrato.
- Ingresos mensuales.

### Sección: Datos del crédito
- Productos solicitados.
- Valor neto sin interés.
- Valor de la cuota.
- Fecha primera cuota.
- Monto aprobado (campo interno/administrativo).
- Número de cuotas.
- Frecuencia de pago:
  - Decadal.
  - Quincenal.
  - Mensual.

### Sección: Observaciones
- Texto libre de observaciones.

### Sección: Autorización de descuento
- Empleador.
- Fecha.
- NIT.
- Nombre del empleado.
- Documento.
- Cargo.
- Descuento por.
- Valor total.
- Firma del solicitante.

### Adjuntos obligatorios
- Documento identidad (frente).
- Documento identidad (reverso).
- Foto usuario sosteniendo documento.

---

## 3) Modelo de datos propuesto (Laravel)

## 3.1 Tabla `credit_applications`
- `id` (uuid o bigIncrements).
- `application_number` (único, legible).
- `status` (`draft`, `submitted`, `approved`, `rejected`).
- `request_date`.
- `full_name`.
- `document_type`, `document_number`, `document_issue_date`.
- `phone_primary`, `phone_secondary`, `email`.
- `residential_address`, `neighborhood`, `city`.
- `company_id` (FK a empresas).
- `work_site`, `hire_date`, `contract_type`, `monthly_income`.
- `requested_products` (json/text).
- `net_value_without_interest`, `installment_value`, `first_installment_date`.
- `approved_amount` (nullable).
- `installments_count`.
- `payment_frequency` (`decadal`, `biweekly`, `monthly`).
- `observations`.
- Campos de autorización descuento:
  - `employer_name`, `employer_nit`, `employee_name`, `employee_document`, `employee_position`.
  - `discount_concept`, `discount_total_value`, `discount_authorization_date`.
- Firma:
  - `signature_path`.
  - `signed_at`.
- OTP:
  - `otp_phone`, `otp_verified_at`.
- PDF:
  - `pdf_path`, `pdf_generated_at`.
- Trazabilidad:
  - `submitted_at`, `approved_at`, `rejected_at`, `reviewed_by`.
- `created_at`, `updated_at`.

### 3.2 Tabla `credit_application_documents`
- `id`.
- `credit_application_id`.
- `type` (`id_front`, `id_back`, `selfie_with_id`).
- `path`.
- `mime_type`, `size`.
- `uploaded_at`.

### 3.3 Tabla `companies`
- `id`.
- `name`.
- `nit`.
- `active`.
- `created_at`, `updated_at`.

### 3.4 Tabla `payment_transactions`
- `id`.
- `credit_application_id`.
- `provider` (`wompi`).
- `provider_transaction_id`.
- `amount`.
- `status`.
- `payload` (json).
- `verified_at`.
- `created_at`, `updated_at`.

---

## 4) Paso a paso técnico recomendado

## Paso 1 — Base del módulo
1. Crear migraciones y modelos para:
   - `credit_applications`
   - `credit_application_documents`
   - `companies`
   - `payment_transactions`
2. Crear policies/permisos para módulo admin.
3. Definir estados y transiciones válidas.

## Paso 2 — Formulario unificado + guardado de progreso
1. Crear wizard en frontend (secciones del formato).
2. Guardar como borrador (`draft`) en cada avance.
3. Implementar recuperación por token seguro o usuario autenticado.
4. Validaciones por sección + validación final de envío.

## Paso 3 — OTP SMS
1. Crear servicio `SmsOtpService` (adaptador de proveedor).
2. Endpoint para enviar OTP y endpoint para verificar OTP.
3. Limitar intentos, expiración y anti-fraude básico.
4. Bloquear envío final si `otp_verified_at` es nulo.

## Paso 4 — Carga de documentos
1. Implementar subida con validaciones (tipo, tamaño, nitidez mínima opcional).
2. Marcar los 3 tipos como requeridos.
3. Evitar envío final con faltantes.
4. Mostrar vista previa antes de enviar.

## Paso 5 — Firma digital
1. Canvas de firma en frontend.
2. Guardar firma como imagen (PNG/SVG).
3. Asociar a la solicitud y registrar `signed_at`.

## Paso 6 — Generación de PDF con diseño base
1. Crear plantilla HTML/CSS para PDF replicando estructura de la imagen:
   - Encabezados por bloques.
   - Tablas para datos personales/laborales/crédito.
   - Sección legal de autorización.
2. Renderizar con DomPDF/Snappy.
3. Incrustar firma digital y datos finales.
4. Guardar PDF en storage y registrar ruta.

## Paso 7 — Panel administrativo
1. Listado de solicitudes con filtros por estado/fecha/empresa.
2. Vista detalle con:
   - Datos del formulario.
   - Descarga de PDF.
   - Visualización de adjuntos.
3. Acciones de estado: aprobar/rechazar (con auditoría).
4. Limitar acceso a 5 usuarios internos activos.

## Paso 8 — CRUD de empresas
1. Crear módulo (listar, crear, editar, activar/desactivar).
2. Relacionar empresas con solicitudes.

## Paso 9 — Wompi
1. Crear integración de pago de cuota quincenal.
2. Guardar transacciones y estado.
3. Implementar webhook de confirmación.
4. Crear módulo de verificación/conciliación para admin.

## Paso 10 — Notificaciones
1. Enviar correo automático al recibir solicitud `submitted`.
2. Plantilla de correo con número de solicitud y enlace admin.
3. (Opcional) Notificación al cliente con acuse.

---

## 5) Reglas de validación clave

- No permitir envío final sin OTP validado.
- No permitir envío final sin los 3 adjuntos requeridos.
- No permitir envío final sin firma.
- Validar consistencia financiera:
  - `installments_count > 0`
  - `installment_value > 0`
  - `net_value_without_interest >= installment_value`
- Validar formatos de documento, correo y teléfonos.

---

## 6) Entregables por sprint sugeridos

### Sprint 1
- Datos + formulario unificado + borrador.
- Carga documental.
- OTP básico.

### Sprint 2
- Firma digital.
- PDF con diseño.
- Envío y notificaciones.

### Sprint 3
- Panel admin completo + estados.
- CRUD empresas.
- Wompi + verificación pagos.

---

## 7) Criterios de aceptación (resumen)

1. El cliente puede crear, pausar y retomar solicitud sin pérdida de datos.
2. El sistema exige OTP, documentos y firma antes de enviar.
3. Al enviar, se genera PDF con estructura similar al formato proporcionado.
4. Admin recibe correo y puede gestionar estado de la solicitud.
5. Admin puede consultar pagos Wompi y catálogo de empresas.

