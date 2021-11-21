<?php

class ImportFromCsv
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'company';
    const DB_USER = 'root';
    const DB_PWD = '';

    const REFS_TABLE_ORGANIZATIONS = 'organizations';
    const REFS_TABLE_POSITIONS = 'positions';
    const REFS_TABLE_TYPE_OF_EMPLOYMENT = 'type_of_employment';

    private $_csv_file = null;
    private $db;
    private $updatedAt;
    private $insertCount = 0;
    private $updateCount = 0;
    private $removeCount = 0;

    private function connectDb()
    {
        try {
            $this->db = new PDO('mysql:host=' . ImportFromCsv::DB_HOST . ';dbname=' . ImportFromCsv::DB_NAME,
                ImportFromCsv::DB_USER,
                ImportFromCsv::DB_PWD, [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
        } catch (PDOException $e) {
            print "Ошибка подключения к БД: " . $e->getMessage();
            die();
        }
    }

    public function findEmployee($fio)
    {
        $query = <<<SQL
SELECT 1 FROM employees WHERE fio = :fio 
SQL;
        $params = [
            'fio' => $fio,
        ];
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    private function findOrCreateReference($name, $refName)
    {
        if ($name === '') return NULL;

        $query = <<<SQL
SELECT id FROM {$refName} WHERE name = :name 
SQL;
        $params = [
            'name' => $name,
        ];
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();

        if ($result) {
            $query = <<<SQL
UPDATE {$refName}
    SET deleted_at = NULL,
        updated_at = :updatedAt
WHERE name = :name;
SQL;
            $params = [
                'name' => $name,
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            ];
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $result;
        }

        $query = <<<SQL
INSERT INTO {$refName} (name, updated_at) 
VALUES (:name, :updatedAt)
SQL;
        $params = [
            'name' => $name,
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $this->db->lastInsertId();
    }

    private function insertEmployee(array $employee)
    {
        $query = <<<SQL
INSERT INTO employees (fio, email, phone, date_birthday, address, organization_id, position_id, type_of_employment_id, date_of_receipt, log, updated_at) 
VALUES (:fio, :email, :phone, :dateBirthday, :address, :organizationId, :positionId, :typeOfEmploymentId, :dateOfReceipt, :log, :updatedAt)
SQL;
        $params = array_merge($employee, [
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ]);
        $stmt = $this->db->prepare($query);

        return $stmt->execute($params);
    }


    private function updateEmployee(array $employee)
    {
        $query = <<<SQL
UPDATE employees 
    SET email = :email, 
        phone = :phone, 
        date_birthday = :dateBirthday, 
        address = :address, 
        organization_id = :organizationId, 
        position_id = :positionId, 
        type_of_employment_id = :typeOfEmploymentId, 
        date_of_receipt = :dateOfReceipt, 
        log = :log,
        updated_at = :updatedAt,
        deleted_at = NULL
WHERE fio = :fio; 
SQL;
        $params = array_merge($employee, [
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ]);
        $stmt = $this->db->prepare($query);

        return $stmt->execute($params);
    }

    private function RemoveNotUsedReference($refName)
    {
        $query = <<<SQL
UPDATE {$refName} 
    SET deleted_at = NOW()
WHERE updated_at != :updatedAt 
    AND deleted_at IS NULL; 
SQL;
        $params = [
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
        $stmt = $this->db->prepare($query);

        return $stmt->execute($params);
    }

    private function removeAllNotUpdatedEmployees()
    {
        $query = <<<SQL
UPDATE employees 
    SET deleted_at = NOW()
WHERE updated_at != :updatedAt 
    AND deleted_at IS NULL; 
SQL;
        $params = [
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        $this->RemoveNotUsedReference(ImportFromCsv::REFS_TABLE_ORGANIZATIONS);
        $this->RemoveNotUsedReference(ImportFromCsv::REFS_TABLE_POSITIONS);
        $this->RemoveNotUsedReference(ImportFromCsv::REFS_TABLE_TYPE_OF_EMPLOYMENT);

        return $stmt->rowCount();
    }

    private function convert(string $str)
    {
        return trim(iconv("Windows-1251", "UTF-8", $str));
    }

    private function tableExist()
    {
        try {
            $this->db->query("SELECT 1 FROM employees");
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    private function init()
    {
        if ($this->tableExist()) return true;

        $sql = <<<SQL
CREATE TABLE `company`.`organizations`
(
    `id`         INT          NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор организации',
    `name`       VARCHAR(300) NOT NULL COMMENT 'Наименование',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT ' Дата обновления записи',
    `deleted_at` TIMESTAMP    NULL COMMENT 'Дата удаления записи',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT = 'Организации';

CREATE TABLE `company`.`positions`
(
    `id`         INT          NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор должности',
    `name`       VARCHAR(300) NOT NULL COMMENT 'Наименование',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT ' Дата обновления записи',
    `deleted_at` TIMESTAMP    NULL COMMENT 'Дата удаления записи',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT = 'Должности';

CREATE TABLE `company`.`type_of_employment`
(
    `id`         INT          NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор вида трудойстройства',
    `name`       VARCHAR(300) NOT NULL COMMENT 'Наименование',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT ' Дата обновления записи',
    `deleted_at` TIMESTAMP    NULL COMMENT 'Дата удаления записи',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT = 'Вид трудоустройства';

CREATE TABLE `company`.`employees`
(
    `id`                    INT          NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор сотрудника',
    `fio`                   VARCHAR(250) NOT NULL COMMENT 'ФИО',
    `email`                 VARCHAR(250) NULL COMMENT 'Адрес электронной почты',
    `phone`                 VARCHAR(50)  NULL COMMENT 'Номер телефона',
    `date_birthday`         DATE         NULL COMMENT 'Дата рождения',
    `address`               VARCHAR(300) NULL COMMENT 'Адрес',
    `organization_id`       INT          NULL COMMENT 'Организация',
    `position_id`           INT          NULL COMMENT 'Должность',
    `type_of_employment_id` INT          NULL COMMENT 'Вид трудоустройства',
    `date_of_receipt`       DATE         NULL COMMENT 'Дата приема',
    `log`                   VARCHAR(300) NULL COMMENT 'Лог',
    `create_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
    `updated_at`            TIMESTAMP    NULL COMMENT 'Дата обновления записи',
    `deleted_at`            TIMESTAMP    NULL COMMENT 'Дата удаления записи',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT = 'Сотрудники компании';

ALTER TABLE `employees`
    ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `employees`
    ADD FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `employees`
    ADD FOREIGN KEY (`type_of_employment_id`) REFERENCES type_of_employment (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
SQL;

        return $this->db->query($sql);
    }

    /**
     * @throws Exception
     */
    public function __construct($csv_file)
    {
        if (file_exists($csv_file)) {
            $this->_csv_file = $csv_file;
        } else {
            throw new Exception("Файл " . $csv_file . " не найден");
        }

        $this->connectDb();

        if (!$this->init()) {
            throw new Exception("БД. Ошибка при создании структуры таблиц");
        }
    }

    public function importEmployeesFromCSV()
    {
        $handle = fopen($this->_csv_file, "r");

        $this->updatedAt = new DateTime('NOW');

        while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
            $line = array_map(array($this, 'convert'), $line);

            if (($line[0] === '') || ($line[0] === 'фио')) continue;

            $line[5] = $this->findOrCreateReference($line[5], ImportFromCsv::REFS_TABLE_ORGANIZATIONS);
            $line[6] = $this->findOrCreateReference($line[6], ImportFromCsv::REFS_TABLE_POSITIONS);
            $line[7] = $this->findOrCreateReference($line[7], ImportFromCsv::REFS_TABLE_TYPE_OF_EMPLOYMENT);

            $employee = new Employee($line);

            if (!$this->findEmployee($line[0])) {
                if ($this->insertEmployee($employee->toArray())) {
                    $this->insertCount++;
                }
            } else {
                if ($this->updateEmployee($employee->toArray())) {
                    $this->updateCount++;
                }
            }
        }
        fclose($handle);

        $this->removeCount = $this->removeAllNotUpdatedEmployees();

        return json_encode([
            'insertCount' => $this->insertCount,
            'updateCount' => $this->updateCount,
            'removeCount' => $this->removeCount
        ]);
    }
}
