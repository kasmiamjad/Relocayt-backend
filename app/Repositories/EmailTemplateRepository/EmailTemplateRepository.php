<?php
declare(strict_types=1);

namespace App\Repositories\EmailTemplateRepository;

use App\Models\EmailTemplate;
use App\Repositories\CoreRepository;
use Illuminate\Support\Facades\Cache;

class EmailTemplateRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return EmailTemplate::class;
    }

    public function paginate(array $filter) {
     
        return $this->model()->paginate($filter['perPage'] ?? 10);
    }

    public function show(EmailTemplate $emailTemplate): EmailTemplate
    {
  
        return $emailTemplate->loadMissing(['emailSetting']);
    }
}
