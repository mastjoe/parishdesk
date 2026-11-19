<?php

namespace App\Http\Controllers\Admin\Sacraments;

use App\Actions\Sacraments\RenderSacramentCertificate;
use App\Http\Controllers\Controller;
use App\Models\SacramentRecord;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SacramentCertificateController extends Controller
{
    /**
     * Stream the certificate for a register entry as a PDF.
     */
    public function show(SacramentRecord $sacrament, RenderSacramentCertificate $render): Response
    {
        Gate::authorize('view', $sacrament);

        abort_if($sacrament->type->certificateTitle() === null, 404);

        return $render->handle($sacrament);
    }
}
