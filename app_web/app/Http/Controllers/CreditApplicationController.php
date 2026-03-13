<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditApplicationController extends Controller
{
    private const PHONE_VERIFICATION_CODE_TTL_MINUTES = 10;

    public function create(Request $request): View
    {
        $application = null;

        if ($request->filled('token')) {
            $application = CreditApplication::where('public_token', $request->string('token'))->first();
        }

        return view('credit-applications.create', [
            'application' => $application,
            'companies' => Company::orderBy('name')->get(['id', 'name', 'nit']),
            'token' => $application?->public_token ?? (string) Str::uuid(),
        ]);
    }


    public function resume(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:60'],
            'phone_primary' => ['required', 'string', 'max:30'],
        ]);

        $application = CreditApplication::query()
            ->where('document_number', $data['document_number'])
            ->where('phone_primary', $data['phone_primary'])
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();

        if (! $application) {
            return back()->withErrors([
                'resume' => 'No encontramos un borrador con esos datos. Verifica número de documento y celular.',
            ])->withInput();
        }

        return redirect()->route('credit-applications.create', [
            'token' => $application->public_token,
        ])->with('status', 'Borrador recuperado correctamente. Puedes continuar tu solicitud.');
    }

    public function store(Request $request)
    {
        $action = $request->input('action', 'draft');
        $isSubmit = $action === 'submit';
        $application = CreditApplication::where('public_token', (string) $request->input('token'))->first();

        $rules = [
            'token' => ['required', 'string'],
            'request_date' => ['nullable', 'date'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'document_issue_date' => ['nullable', 'date'],
            'phone_primary' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'work_site' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
            'contract_type' => ['nullable', 'string', 'max:120'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'requested_products' => ['nullable', 'string'],
            'net_value_without_interest' => ['nullable', 'numeric', 'min:0'],
            'installment_value' => ['nullable', 'numeric', 'min:0'],
            'first_installment_date' => ['nullable', 'date'],
            'installments_count' => ['nullable', 'integer', 'min:1'],
            'payment_frequency' => ['nullable', Rule::in(['decadal', 'biweekly', 'monthly'])],
            'observations' => ['nullable', 'string'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'discount_authorization_date' => ['nullable', 'date'],
            'employer_nit' => ['nullable', 'string', 'max:60'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_document' => ['nullable', 'string', 'max:60'],
            'employee_position' => ['nullable', 'string', 'max:120'],
            'discount_concept' => ['nullable', 'string', 'max:255'],
            'discount_total_value' => ['nullable', 'numeric', 'min:0'],
            'signature_data' => ['nullable', 'string'],
            'id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'selfie_with_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'remove_id_front' => ['nullable', 'boolean'],
            'remove_id_back' => ['nullable', 'boolean'],
            'remove_selfie_with_id' => ['nullable', 'boolean'],
            'remove_signature' => ['nullable', 'boolean'],
        ];

        if ($isSubmit) {
            $requiredFields = [
                'request_date', 'full_name', 'document_type', 'document_number', 'phone_primary',
                'email', 'residential_address', 'city', 'company_id', 'monthly_income',
                'requested_products', 'installment_value', 'installments_count', 'payment_frequency',
                'employer_name', 'employee_name',
            ];

            foreach ($requiredFields as $field) {
                $rules[$field][0] = 'required';
            }

            $hasStoredSignature = (bool) $application?->signature_path;
            $rules['signature_data'] = $hasStoredSignature
                ? ['nullable', 'string']
                : ['required', 'string'];
        }

        $data = $request->validate($rules);
        $data = $this->syncAuthorizationFields($data);

        $application = $application ?: CreditApplication::firstOrNew([
            'public_token' => $data['token'],
        ]);

        $application->fill($data);
        $application->status = $isSubmit ? 'submitted' : 'draft';

        if (($data['phone_primary'] ?? null) && $application->phone_verified_at && $application->phone_verified_number !== $this->normalizePhone($data['phone_primary'])) {
            $application->phone_verified_at = null;
            $application->phone_verified_number = null;
            $application->phone_verification_code_hash = null;
            $application->phone_verification_expires_at = null;
        }

        $basePath = "credit-applications/{$data['token']}";

        $removableFiles = [
            'id_front' => 'id_front_path',
            'id_back' => 'id_back_path',
            'selfie_with_id' => 'selfie_with_id_path',
        ];

        foreach ($removableFiles as $fileField => $modelField) {
            if (! empty($data['remove_' . $fileField])) {
                $this->deletePublicFile($application->{$modelField});
                $application->{$modelField} = null;
            }
        }

        if (! empty($data['remove_signature'])) {
            $this->deletePublicFile($application->signature_path);
            $application->signature_path = null;
        }

        foreach (['id_front', 'id_back', 'selfie_with_id'] as $fileField) {
            if ($request->hasFile($fileField)) {
                $modelField = $fileField . '_path';
                $this->deletePublicFile($application->{$modelField});
                $path = $this->storePublicFile($request->file($fileField), $basePath, $fileField);
                $application->{$modelField} = $path;
            }
        }

        if (! empty($data['signature_data'])) {
            $previousSignaturePath = $application->signature_path;
            $signaturePath = $this->saveSignature($data['signature_data'], $basePath);

            if ($signaturePath !== null) {
                if ($previousSignaturePath && $previousSignaturePath !== $signaturePath) {
                    $this->deletePublicFile($previousSignaturePath);
                }

                $application->signature_path = $signaturePath;
            }
        }

        if ($isSubmit) {
            if (! empty($data['remove_signature']) && empty($data['signature_data'])) {
                return back()->withErrors([
                    'signature_data' => 'Debes volver a firmar antes de enviar la solicitud.',
                ])->withInput();
            }

            if (! $application->phone_verified_at || $application->phone_verified_number !== $this->normalizePhone((string) $application->phone_primary)) {
                return back()->withErrors([
                    'phone_verification' => 'Debes validar tu celular por código SMS antes de enviar la solicitud.',
                ])->withInput();
            }

            if (! $application->id_front_path || ! $application->id_back_path || ! $application->selfie_with_id_path) {
                return back()->withErrors([
                    'documents' => 'Debes adjuntar cédula frente, cédula reverso y foto sosteniendo la cédula para enviar la solicitud.',
                ])->withInput();
            }

            if (! $application->signature_path) {
                return back()->withErrors([
                    'signature_data' => 'La firma en pantalla es obligatoria para enviar la solicitud.',
                ])->withInput();
            }

            $application->submitted_at = now();
        }

        $application->save();

        if ($request->ajax() && ! $isSubmit) {
            return response()->noContent();
        }

        if ($isSubmit) {
            $pdfPath = $this->generatePdf($application, $basePath);
            $application->pdf_path = $pdfPath;
            $application->save();

            return redirect()
                ->route('credit-applications.create', ['token' => $application->public_token])
                ->with('status', 'Solicitud enviada correctamente. PDF generado.')
                ->with('resume_url', route('credit-applications.create', ['token' => $application->public_token]));
        }

        return redirect()
            ->route('credit-applications.create', ['token' => $application->public_token])
            ->with('status', 'Borrador guardado correctamente.')
            ->with('resume_url', route('credit-applications.create', ['token' => $application->public_token]));
    }

    public function sendPhoneCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'phone_primary' => ['required', 'string', 'max:30'],
        ]);

        $phone = $this->normalizePhone($data['phone_primary']);

        if (! preg_match('/^57\d{10}$/', $phone)) {
            return back()->withErrors([
                'phone_primary' => 'Ingresa un celular válido de Colombia (10 dígitos, con o sin prefijo 57).',
            ])->withInput();
        }

        $application = CreditApplication::firstOrNew([
            'public_token' => $data['token'],
        ]);

        $this->saveDraftSnapshot($application, $request->all());

        $code = (string) random_int(100000, 999999);
        $message = "Tu código de verificación BYB Store es: {$code}. Vence en " . self::PHONE_VERIFICATION_CODE_TTL_MINUTES . ' minutos.';

        $sent = $this->sendSms($phone, $message);

        if (! $sent) {
            /*return back()->withErrors([
                'phone_verification' => 'No pudimos enviar el SMS en este momento. Intenta de nuevo.',
            ])->withInput();*/
        }

        $application->phone_verification_code_hash = Hash::make($code);
        $application->phone_verification_expires_at = now()->addMinutes(self::PHONE_VERIFICATION_CODE_TTL_MINUTES);
        $application->phone_verified_at = null;
        $application->phone_verified_number = null;
        $application->save();

        $response = redirect()->route('credit-applications.create', ['token' => $application->public_token])
            ->with('status', 'Te enviamos un código por SMS. Ingrésalo para validar tu celular.');

        if (config('app.debug')) {
            $response->with('phone_verification_code_preview', $code);
        }

        return $response;
    }

    public function verifyPhoneCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'phone_primary' => ['required', 'string', 'max:30'],
            'verification_code' => ['required', 'digits:6'],
        ]);

        $application = CreditApplication::where('public_token', $data['token'])->first();

        if (! $application || ! $application->phone_verification_code_hash) {
            return back()->withErrors([
                'phone_verification' => 'Primero debes solicitar el código SMS.',
            ])->withInput();
        }

        $phone = $this->normalizePhone($data['phone_primary']);

        if ($application->phone_verification_expires_at?->isPast()) {
            return back()->withErrors([
                'phone_verification' => 'El código expiró. Solicita uno nuevo.',
            ])->withInput();
        }

        if (! Hash::check($data['verification_code'], $application->phone_verification_code_hash)) {
            return back()->withErrors([
                'verification_code' => 'El código ingresado no es válido.',
            ])->withInput();
        }

        $this->saveDraftSnapshot($application, $request->all());
        $application->phone_verified_number = $phone;
        $application->phone_verified_at = now();
        $application->phone_verification_code_hash = null;
        $application->phone_verification_expires_at = null;
        $application->save();

        return redirect()->route('credit-applications.create', ['token' => $application->public_token])
            ->with('status', 'Celular validado correctamente. Ya puedes enviar la solicitud.');
    }

    public function downloadPdf(CreditApplication $creditApplication)
    {
        $pdfAbsolutePath = $creditApplication->pdf_path ? public_path($creditApplication->pdf_path) : null;

        abort_unless($pdfAbsolutePath && file_exists($pdfAbsolutePath), 404);

        return response()->download($pdfAbsolutePath, "solicitud-{$creditApplication->id}.pdf");
    }

    private function saveSignature(string $signatureData, string $basePath): ?string
    {
        if (! str_contains($signatureData, 'base64,')) {
            return null;
        }

        $rawData = substr($signatureData, strpos($signatureData, 'base64,') + 7);
        $rawData = preg_replace('/\s+/', '', $rawData) ?? '';
        $decoded = base64_decode($rawData, true);

        if ($decoded === false) {
            return null;
        }

        $signaturePath = $basePath . '/signature.png';

        $fullPath = public_path($signaturePath);
        $this->ensurePublicDirectoryExists(dirname($fullPath));
        file_put_contents($fullPath, $decoded);

        return $signaturePath;
    }

    private function generatePdf(CreditApplication $application, string $basePath): string
    {
        $pdf = Pdf::loadView('credit-applications.pdf', [
            'application' => $application,
        ])->setPaper('a4');

        $path = $basePath . '/solicitud.pdf';
        $fullPath = public_path($path);
        $this->ensurePublicDirectoryExists(dirname($fullPath));
        file_put_contents($fullPath, $pdf->output());

        return $path;
    }

    private function storePublicFile(UploadedFile $file, string $basePath, string $fileField): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = $fileField . '.' . strtolower($extension);
        $destinationDirectory = public_path($basePath);

        $this->ensurePublicDirectoryExists($destinationDirectory);

        $file->move($destinationDirectory, $filename);

        return $basePath . '/' . $filename;
    }

    private function ensurePublicDirectoryExists(string $directoryPath): void
    {
        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }
    }


    private function deletePublicFile(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $fullPath = public_path($relativePath);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '57') && strlen($digits) === 12) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '57' . $digits;
        }

        return $digits;
    }

    private function sendSms(string $phone, string $message): bool
    {
        $apiKey = (string) config('services.hablame.api_key');
        $sender = (string) config('services.hablame.sender');

        if (! $apiKey || ! $sender) {
            return false;
        }

        $response = Http::asJson()
            ->withToken($apiKey)
            ->post('https://api103.hablame.co/api/sms/v3/send/marketing', [
                'toNumber' => $phone,
                'sms' => $message,
                'flash' => false,
                'sc' => $sender,
                'request_dlvr_rcpt' => 0,
            ]);

        return $response->successful();
    }

    private function saveDraftSnapshot(CreditApplication $application, array $payload): void
    {
        $allowedFields = [
            'request_date', 'full_name', 'document_type', 'document_number', 'document_issue_date',
            'phone_primary', 'phone_secondary', 'email', 'residential_address', 'neighborhood', 'city',
            'company_id', 'work_site', 'hire_date', 'contract_type', 'monthly_income',
            'requested_products', 'net_value_without_interest', 'installment_value',
            'first_installment_date', 'installments_count', 'payment_frequency', 'observations',
            'employer_name', 'discount_authorization_date', 'employer_nit', 'employee_name',
            'employee_document', 'employee_position', 'discount_concept', 'discount_total_value',
        ];

        $data = collect($payload)
            ->only($allowedFields)
            ->all();

        $data = $this->syncAuthorizationFields($data);

        $application->fill($data);
        $application->status = $application->status ?: 'draft';

        if (($data['phone_primary'] ?? null) && $application->phone_verified_at && $application->phone_verified_number !== $this->normalizePhone((string) $data['phone_primary'])) {
            $application->phone_verified_at = null;
            $application->phone_verified_number = null;
            $application->phone_verification_code_hash = null;
            $application->phone_verification_expires_at = null;
        }
    }

    private function syncAuthorizationFields(array $data): array
    {
        if (! empty($data['company_id'])) {
            $company = Company::find($data['company_id']);

            if ($company) {
                $data['employer_name'] = $company->name;
                $data['employer_nit'] = $company->nit;
            }
        }

        if (! empty($data['full_name'])) {
            $data['employee_name'] = $data['full_name'];
        }

        if (! empty($data['document_number'])) {
            $data['employee_document'] = $data['document_number'];
        }

        return $data;
    }
}
