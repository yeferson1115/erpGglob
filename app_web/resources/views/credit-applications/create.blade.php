<x-public-layout>
    <div class="container py-4">
        <div class="card">
            <div class="card-header credit-form-header text-white">
                <h4 class="mb-0">Solicitud de crédito + autorización de descuento</h4>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif


                @if (session('phone_verification_code_preview'))
                    <div class="alert alert-warning">
                        <strong>Modo pruebas:</strong> código temporal para validar celular:
                        <span class="badge bg-dark">{{ session('phone_verification_code_preview') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('resume_url'))
                    <div class="alert alert-info">
                        <div><strong>Enlace para retomar:</strong></div>
                        <div><a href="{{ session('resume_url') }}">{{ session('resume_url') }}</a></div>
                        <small>Guárdalo para continuar luego sin perder tu progreso.</small>
                    </div>
                @endif

                <div id="autosave-status" class="small text-muted mb-3"></div>

                <div class="card border mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">¿Ya habías iniciado una solicitud?</h5>
                        <form action="{{ route('credit-applications.resume') }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Número de documento</label>
                                <input class="form-control" name="document_number" value="{{ old('document_number') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Celular principal</label>
                                <input class="form-control" name="phone_primary" value="{{ old('phone_primary') }}" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-brand w-100">Retomar solicitud</button>
                            </div>
                        </form>
                    </div>
                </div>
                <form action="{{ route('credit-applications.store') }}" method="POST" enctype="multipart/form-data" id="credit-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ old('token', $token) }}">
                    <input type="hidden" name="signature_data" id="signature_data">
                    <input type="hidden" name="remove_signature" id="remove_signature" value="0">

                    <h5>Datos personales</h5>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Fecha solicitud</label><input type="date" class="form-control" name="request_date" value="{{ old('request_date', optional($application?->request_date)->format('Y-m-d')) }}"></div>
                        <div class="col-md-8"><label class="form-label">Nombres y apellidos</label><input class="form-control" id="full_name" name="full_name" value="{{ old('full_name', $application?->full_name) }}"></div>
                        <div class="col-md-3"><label class="form-label">Tipo documento</label><input class="form-control" name="document_type" value="{{ old('document_type', $application?->document_type) }}"></div>
                        <div class="col-md-3"><label class="form-label">Número documento</label><input class="form-control" id="document_number" name="document_number" value="{{ old('document_number', $application?->document_number) }}"></div>
                        <div class="col-md-3"><label class="form-label">Celular 1</label><input class="form-control" name="phone_primary" value="{{ old('phone_primary', $application?->phone_primary) }}"></div>
                        <div class="col-md-3"><label class="form-label">Celular 2</label><input class="form-control" name="phone_secondary" value="{{ old('phone_secondary', $application?->phone_secondary) }}"></div>
                        <div class="col-md-6"><label class="form-label">Correo</label><input type="email" class="form-control" name="email" value="{{ old('email', $application?->email) }}"></div>
                        <div class="col-md-6"><label class="form-label">Dirección residencia</label><input class="form-control" name="residential_address" value="{{ old('residential_address', $application?->residential_address) }}"></div>
                        <div class="col-md-6"><label class="form-label">Barrio</label><input class="form-control" name="neighborhood" value="{{ old('neighborhood', $application?->neighborhood) }}"></div>
                        <div class="col-md-6"><label class="form-label">Ciudad</label><input class="form-control" name="city" value="{{ old('city', $application?->city) }}"></div>
                    </div>

                    <div class="alert {{ $application?->phone_verified_at ? 'alert-success' : 'alert-warning' }} mt-3 mb-0">
                        @if ($application?->phone_verified_at)
                            ✅ Celular validado: {{ $application->phone_primary }}.
                        @else
                            ⚠️ Antes de enviar la solicitud debes validar el celular principal por SMS.
                        @endif
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Código de verificación</label>
                            <input class="form-control" name="verification_code" maxlength="6" placeholder="123456">
                        </div>
                        <div class="col-md-6 d-flex align-items-end gap-2">
                            <button class="btn btn-outline-primary" type="submit" formaction="{{ route('credit-applications.send-phone-code') }}" formmethod="POST" name="action" value="send_code">Enviar código SMS</button>
                            <button class="btn btn-success" type="submit" formaction="{{ route('credit-applications.verify-phone-code') }}" formmethod="POST" name="action" value="verify_code">Validar celular</button>
                        </div>
                    </div>

                    <hr>
                    <h5>Datos laborales y crédito</h5>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Empresa donde labora</label>
                            <select class="form-control" id="company_id" name="company_id">
                                <option value="">Selecciona una empresa</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" data-company-name="{{ $company->name }}" data-company-nit="{{ $company->nit }}" @selected((string) old('company_id', $application?->company_id) === (string) $company->id)>
                                        {{ $company->name }} - NIT {{ $company->nit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Sede</label><input class="form-control" name="work_site" value="{{ old('work_site', $application?->work_site) }}"></div>
                        <div class="col-md-4"><label class="form-label">Tipo contrato</label><input class="form-control" name="contract_type" value="{{ old('contract_type', $application?->contract_type) }}"></div>
                        <div class="col-md-4"><label class="form-label">Ingresos mensuales</label><input type="number" step="0.01" class="form-control" name="monthly_income" value="{{ old('monthly_income', $application?->monthly_income) }}"></div>
                        <div class="col-md-4"><label class="form-label">Fecha ingreso</label><input type="date" class="form-control" name="hire_date" value="{{ old('hire_date', optional($application?->hire_date)->format('Y-m-d')) }}"></div>
                        <div class="col-md-12"><label class="form-label">Productos solicitados</label><textarea class="form-control" name="requested_products" rows="2">{{ old('requested_products', $application?->requested_products) }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Valor neto sin interés</label><input type="number" step="0.01" class="form-control" name="net_value_without_interest" value="{{ old('net_value_without_interest', $application?->net_value_without_interest) }}"></div>
                        <div class="col-md-4"><label class="form-label">Valor cuota</label><input type="number" step="0.01" class="form-control" name="installment_value" value="{{ old('installment_value', $application?->installment_value) }}"></div>
                        <div class="col-md-4"><label class="form-label">Número de cuotas</label><input type="number" class="form-control" name="installments_count" value="{{ old('installments_count', $application?->installments_count) }}"></div>
                        <div class="col-md-4"><label class="form-label">Frecuencia</label>
                            <select class="form-control" name="payment_frequency">
                                <option value="">Selecciona</option>
                                @foreach (['decadal' => 'Decadal', 'biweekly' => 'Quincenal', 'monthly' => 'Mensual'] as $key => $label)
                                    <option value="{{ $key }}" @selected(old('payment_frequency', $application?->payment_frequency) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8"><label class="form-label">Observaciones</label><input class="form-control" name="observations" value="{{ old('observations', $application?->observations) }}"></div>
                    </div>

                    <hr>
                    <h5>Autorización de descuento</h5>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Empleador</label><input class="form-control" id="employer_name" name="employer_name" value="{{ old('employer_name', $application?->employer_name) }}" readonly></div>
                        <div class="col-md-6"><label class="form-label">NIT</label><input class="form-control" id="employer_nit" name="employer_nit" value="{{ old('employer_nit', $application?->employer_nit) }}" readonly></div>
                        <div class="col-md-4"><label class="form-label">Nombre empleado</label><input class="form-control" id="employee_name" name="employee_name" value="{{ old('employee_name', $application?->employee_name) }}" readonly></div>
                        <div class="col-md-4"><label class="form-label">Documento</label><input class="form-control" id="employee_document" name="employee_document" value="{{ old('employee_document', $application?->employee_document) }}" readonly></div>
                        <div class="col-md-4"><label class="form-label">Cargo</label><input class="form-control" name="employee_position" value="{{ old('employee_position', $application?->employee_position) }}"></div>
                        <div class="col-md-6"><label class="form-label">Descuento por</label><input class="form-control" name="discount_concept" value="{{ old('discount_concept', $application?->discount_concept) }}"></div>
                        <div class="col-md-3"><label class="form-label">Valor total</label><input type="number" step="0.01" class="form-control" name="discount_total_value" value="{{ old('discount_total_value', $application?->discount_total_value) }}"></div>
                        <div class="col-md-3"><label class="form-label">Fecha</label><input type="date" class="form-control" name="discount_authorization_date" value="{{ old('discount_authorization_date', optional($application?->discount_authorization_date)->format('Y-m-d')) }}"></div>
                    </div>

                    <hr>
                    <h5>Adjuntos obligatorios</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cédula frente</label>
                            <input type="file" class="form-control" name="id_front">
                            @if ($application?->id_front_path)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_id_front" value="1" id="remove_id_front">
                                    <label class="form-check-label" for="remove_id_front">Eliminar archivo actual</label>
                                </div>
                                <a href="{{ asset($application->id_front_path) }}" target="_blank" class="small">Ver archivo guardado</a>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cédula reverso</label>
                            <input type="file" class="form-control" name="id_back">
                            @if ($application?->id_back_path)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_id_back" value="1" id="remove_id_back">
                                    <label class="form-check-label" for="remove_id_back">Eliminar archivo actual</label>
                                </div>
                                <a href="{{ asset($application->id_back_path) }}" target="_blank" class="small">Ver archivo guardado</a>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selfie con cédula</label>
                            <input type="file" class="form-control" name="selfie_with_id">
                            @if ($application?->selfie_with_id_path)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_selfie_with_id" value="1" id="remove_selfie_with_id">
                                    <label class="form-check-label" for="remove_selfie_with_id">Eliminar archivo actual</label>
                                </div>
                                <a href="{{ asset($application->selfie_with_id_path) }}" target="_blank" class="small">Ver archivo guardado</a>
                            @endif
                        </div>
                    </div>

                    <hr>
                    <h5>Firma en pantalla (obligatoria al enviar)</h5>
                    <div class="border rounded p-2 bg-light">
                        <canvas id="signature-pad" width="800" height="220" style="width:100%;max-width:100%;border:1px dashed #6c757d;background:#fff"></canvas>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-signature">Limpiar firma</button>
                        </div>
                    </div>

                    @if ($application?->signature_path)
                        <div class="mt-2">
                            <a href="{{ asset($application->signature_path) }}" target="_blank" class="small d-inline-block">Ver firma guardada</a>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="remove_signature_checkbox">
                                <label class="form-check-label" for="remove_signature_checkbox">Eliminar firma guardada</label>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-outline-brand" type="submit" name="action" value="draft">Guardar borrador</button>
                        <button class="btn btn-brand" type="submit" name="action" value="submit">Enviar solicitud</button>
                        @if ($application?->pdf_path)
                            <a class="btn btn-success" href="{{ route('credit-applications.pdf', $application) }}">Descargar PDF</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const canvas = document.getElementById('signature-pad');
            const hiddenInput = document.getElementById('signature_data');
            const removeSignatureInput = document.getElementById('remove_signature');
            const removeSignatureCheckbox = document.getElementById('remove_signature_checkbox');
            const clearBtn = document.getElementById('clear-signature');
            const form = document.getElementById('credit-form');
            const formActionUrl = form?.getAttribute('action') || window.location.href;
            const autosaveStatus = document.getElementById('autosave-status');
            const fullNameInput = document.getElementById('full_name');
            const documentNumberInput = document.getElementById('document_number');
            const companySelect = document.getElementById('company_id');
            const employerNameInput = document.getElementById('employer_name');
            const employerNitInput = document.getElementById('employer_nit');
            const employeeNameInput = document.getElementById('employee_name');
            const employeeDocumentInput = document.getElementById('employee_document');
            const ctx = canvas.getContext('2d');
            let drawing = false;
            let autosaveTimer;
            let hasSignatureStroke = false;

            const syncDiscountAuthorizationFields = () => {
                const selectedOption = companySelect?.options?.[companySelect.selectedIndex];
                const companyName = selectedOption?.dataset?.companyName || '';
                const companyNit = selectedOption?.dataset?.companyNit || '';

                if (employerNameInput) {
                    employerNameInput.value = companyName;
                }

                if (employerNitInput) {
                    employerNitInput.value = companyNit;
                }

                if (employeeNameInput) {
                    employeeNameInput.value = fullNameInput?.value || '';
                }

                if (employeeDocumentInput) {
                    employeeDocumentInput.value = documentNumberInput?.value || '';
                }
            };

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#111827';

            const position = (e) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: (clientX - rect.left) * (canvas.width / rect.width),
                    y: (clientY - rect.top) * (canvas.height / rect.height),
                };
            };

            const start = (e) => {
                drawing = true;
                const p = position(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                hasSignatureStroke = true;
                e.preventDefault();
            };

            const move = (e) => {
                if (!drawing) {
                    return;
                }
                const p = position(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                hasSignatureStroke = true;
                e.preventDefault();
            };

            const end = () => {
                drawing = false;
                if (hasSignatureStroke) {
                    hiddenInput.value = canvas.toDataURL('image/png');
                }
                if (removeSignatureInput) {
                    removeSignatureInput.value = '0';
                }
                if (removeSignatureCheckbox) {
                    removeSignatureCheckbox.checked = false;
                }
                scheduleAutosave();
            };

            ['mousedown', 'touchstart', 'pointerdown'].forEach(evt => canvas.addEventListener(evt, start, { passive: false }));
            ['mousemove', 'touchmove', 'pointermove'].forEach(evt => canvas.addEventListener(evt, move, { passive: false }));
            ['mouseup', 'mouseleave', 'touchend', 'pointerup', 'pointerleave'].forEach(evt => canvas.addEventListener(evt, end));

            clearBtn.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
                hasSignatureStroke = false;
                if (removeSignatureInput) {
                    removeSignatureInput.value = '1';
                }
            });

            const autosave = async () => {
                const formData = new FormData(form);
                formData.set('action', 'draft');
                formData.delete('verification_code');

                autosaveStatus.textContent = 'Guardando borrador...';

                try {
                    const response = await fetch(formActionUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    autosaveStatus.textContent = response.ok
                        ? 'Borrador guardado automáticamente.'
                        : 'No se pudo guardar el borrador automático.';
                } catch (error) {
                    autosaveStatus.textContent = 'No se pudo guardar el borrador automático.';
                }
            };

            const scheduleAutosave = () => {
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(autosave, 1200);
            };

            setInterval(autosave, 30000);

            removeSignatureCheckbox?.addEventListener('change', () => {
                if (removeSignatureInput) {
                    removeSignatureInput.value = removeSignatureCheckbox.checked ? '1' : '0';
                }
                scheduleAutosave();
            });

            companySelect?.addEventListener('change', () => {
                syncDiscountAuthorizationFields();
                scheduleAutosave();
            });
            fullNameInput?.addEventListener('input', syncDiscountAuthorizationFields);
            documentNumberInput?.addEventListener('input', syncDiscountAuthorizationFields);

            syncDiscountAuthorizationFields();

            form.querySelectorAll('input, select, textarea').forEach((field) => {
                if (field.name === 'verification_code') {
                    return;
                }

                field.addEventListener('input', scheduleAutosave);
                field.addEventListener('change', scheduleAutosave);
            });

            form.addEventListener('submit', () => {
                if (hasSignatureStroke) {
                    hiddenInput.value = canvas.toDataURL('image/png');
                }
                if (removeSignatureInput && removeSignatureCheckbox?.checked) {
                    removeSignatureInput.value = '1';
                }
            });
        })();
    </script>
</x-public-layout>
