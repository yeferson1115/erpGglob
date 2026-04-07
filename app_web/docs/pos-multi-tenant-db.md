# Diseño propuesto: multi-tenant real + POS distribuido

## Objetivo
Organizar el POS para que toda la trazabilidad quede por:

1. **Empresa (tenant)**
2. **Punto de venta (sucursal)**
3. **Caja**
4. **Turno de cajero (usuario en caja en una ventana de tiempo)**

## Estructura final en base de datos

### 1) Jerarquía organizacional
- `companies`
- `sales_points` (`company_id`)
- `cash_registers` (`company_id`, `sales_point_id`)

### 2) Asignación de usuarios
- `sales_point_user`
  - Permite que el dueño asigne cajeros a uno o varios puntos de venta.
  - Controla estado activo de la asignación (`is_active`).
- `cash_register_user`
  - Mantiene compatibilidad para permisos por caja.

### 3) Turnos operativos reales
- `cash_register_shifts`
  - Define quién usa qué caja y en qué horario (`starts_at`, `ends_at`).
  - Ejemplo: misma caja con Pepito 1 en la mañana y Pepito 2 en la tarde.

### 4) Catálogo por punto de venta
- `product_categories` y `inventory_products` con `company_id` para aislamiento tenant.
- `sales_point_product_category` para publicar categorías por punto.
- `sales_point_inventory_product` para publicar productos por punto y manejar disponibilidad/stock local.

### 5) Trazabilidad transaccional POS
Se amplió en:
- `pos_shifts`
- `pos_sales`
- `pos_cash_movements`

Ahora pueden guardar:
- `sales_point_id`
- `cash_register_id`
- `cash_register_shift_id`

Con esto cada venta/movimiento queda ligada al turno real.

## Flujo recomendado de operación
1. Dueño crea sucursales (`sales_points`) y cajas (`cash_registers`).
2. Dueño asigna cajeros a sucursal (`sales_point_user`).
3. Al iniciar jornada se crea `cash_register_shifts` (open).
4. Toda venta/movimiento referencia ese turno.
5. Al cerrar caja se cierra el turno (`ends_at`, `status=closed`).

## Resultado
Se logra el modelo solicitado: **un usuario puede trabajar en varios puntos de venta, pero en un momento específico trabaja en una sola caja mediante un turno**.
