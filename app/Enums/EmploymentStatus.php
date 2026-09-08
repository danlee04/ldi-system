<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Permanent = 'permanent';
    case JobOrder = 'job_order';
    case ContractOfService = 'contract_of_service';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
