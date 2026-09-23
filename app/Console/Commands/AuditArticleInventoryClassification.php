<?php

namespace App\Console\Commands;

use App\Services\ArticleInventoryClassificationAuditService;
use Illuminate\Console\Command;

class AuditArticleInventoryClassification extends Command
{
    protected $signature = 'articles:audit-inventory-classification {--apply : Clasifica únicamente candidatos con evidencia física}';

    protected $description = 'Audita la clasificación producto/servicio e inventariable de los artículos';

    public function handle(ArticleInventoryClassificationAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $result = $service->analyze($apply);

        $this->info($apply ? 'Modo APPLY explícito.' : 'Modo DRY-RUN: no se modificó ningún artículo.');
        $this->table(['Grupo', 'Cantidad', 'IDs'], [
            ['Total', $result['total'], '-'],
            ['Ya clasificados', count($result['already_classified']), implode(', ', $result['already_classified']) ?: '-'],
            ['Candidatos inventariables', count($result['inventory_candidates']), implode(', ', $result['inventory_candidates']) ?: '-'],
            ['Ambiguos/sin evidencia', count($result['ambiguous_without_evidence']), implode(', ', $result['ambiguous_without_evidence']) ?: '-'],
            ['Conflictos', count($result['conflicts']), implode(', ', $result['conflicts']) ?: '-'],
            ['Aplicados', $result['applied'], '-'],
        ]);

        return self::SUCCESS;
    }
}
