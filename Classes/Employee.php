<?php

class Employee
{
    private $fio;
    private $email;
    private $phone;
    private $dateBirthday;
    private $address;
    private $organizationId;
    private $positionId;
    private $typeOfEmploymentId;
    private $dateOfReceipt;
    private $log;

    public function __construct(array $employee)
    {
        $logErrors = array();

        $this->fio = $employee[0];

        if (filter_var($employee[1], FILTER_VALIDATE_EMAIL) !== false) {
            $this->email = $employee[1];
        } else {
            $logErrors[] = 'Не корректный адрес электронной почты';
        }

        if ($this->validatePhoneNumber($employee[2])) {
            $this->phone = $this->validatePhoneNumber($employee[2]);
        } else {
            $logErrors[] = 'Не корректный номер телефона';
        }

        if ($this->validateAndConvertDate($employee[3]) && $employee[3] !== '') {
            $this->dateBirthday = $this->validateAndConvertDate($employee[3]);
        } else {
            $logErrors[] = 'Не корректная дата рождения';
        }

        $this->address            = $employee[4];
        $this->organizationId     = $employee[5];
        $this->positionId         = $employee[6];
        $this->typeOfEmploymentId = $employee[7];

        if ($this->validateAndConvertDate($employee[8]) && $employee[8] !== '') {
            $this->dateOfReceipt =$this->validateAndConvertDate($employee[8]);
        } else {
            $logErrors[] = 'Не корректная дата приема на работу';
        }

        $this->log = implode(';', $logErrors);
    }

    private function validatePhoneNumber($phone)
    {
        $phone = trim((string)$phone);
        if (!$phone) return false;
        $phone = preg_replace('#[^0-9+]+#uis', '', $phone);
        if (!preg_match('#^(?:\\+?7|8|)(.*?)$#uis', $phone, $m)) return false;
        $phone = '+7' . preg_replace('#[^0-9]+#uis', '', $m[1]);
        if (!preg_match('#^\\+7[0-9]{10}$#uis', $phone, $m)) return false;

        return $phone;
    }

    private function validateAndConvertDate($dateString)
    {
        $convertDate = DateTime::createFromFormat('d.m.Y', $dateString);

        if (!$convertDate) return false;
            if ($convertDate->format('d.m.Y') !== $dateString) return false;

        return $convertDate->format('Y-m-d');
    }

    public function toArray(): array
    {
        return [
            'fio'                => $this->fio,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'dateBirthday'       => $this->dateBirthday,
            'address'            => $this->address,
            'organizationId'     => $this->organizationId,
            'positionId'         => $this->positionId,
            'typeOfEmploymentId' => $this->typeOfEmploymentId,
            'dateOfReceipt'      => $this->dateOfReceipt,
            'log'                => $this->log,
        ];
    }
}
