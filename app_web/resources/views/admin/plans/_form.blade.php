<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Nombre del plan</label>
        <input name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required>
    </div>

    <div class="col-md-2"><input type="hidden" name="gglob_cloud_enabled" value="0"><label><input type="checkbox" name="gglob_cloud_enabled" value="1" @checked(old('gglob_cloud_enabled', $plan->gglob_cloud_enabled ?? false))> Activar Gglob Nube</label></div>
    <div class="col-md-2"><input type="hidden" name="gglob_pay_enabled" value="0"><label><input type="checkbox" name="gglob_pay_enabled" value="1" @checked(old('gglob_pay_enabled', $plan->gglob_pay_enabled ?? false))> Activar Gglob Pay</label></div>
    <div class="col-md-2"><input type="hidden" name="gglob_pos_enabled" value="0"><label><input type="checkbox" name="gglob_pos_enabled" value="1" @checked(old('gglob_pos_enabled', $plan->gglob_pos_enabled ?? false))> Activar Gglob POS</label></div>
    <div class="col-md-2"><input type="hidden" name="gglob_accounting_enabled" value="0"><label><input type="checkbox" name="gglob_accounting_enabled" value="1" @checked(old('gglob_accounting_enabled', $plan->gglob_accounting_enabled ?? false))> Activar Gglob Contable</label></div>

    <div class="col-md-2">
        <label class="form-label">POS modo</label>
        <select name="pos_mode" class="form-select">
            <option value="mono" @selected(old('pos_mode', $plan->pos_mode ?? 'mono')==='mono')>MonoCaja</option>
            <option value="multi" @selected(old('pos_mode', $plan->pos_mode ?? 'mono')==='multi')>MultiCaja</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label"># Cajas</label>
        <input type="number" min="1" max="50" name="pos_boxes" class="form-control" value="{{ old('pos_boxes', $plan->pos_boxes ?? 1) }}">
    </div>
</div>
