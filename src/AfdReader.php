<?php

namespace Convenia\AfdReader;

use Convenia\AfdReader\Exception\FileNotFoundException;
use Convenia\AfdReader\Exception\WrongFileTypeException;

class AfdReader
{
    private $file;
    private $fileType;
    private $fileContents;
    private $fileArray = [];
    private $userArray = [];
    private $typePosition = 9;
    private $typeNumber = [
        'Afdt'  => [
            '1' => \Convenia\AfdReader\Registry\Afdt\Header::class,
            '2' => \Convenia\AfdReader\Registry\Afdt\Detail::class,
            '9' => \Convenia\AfdReader\Registry\Afdt\Trailer::class,
        ],
        'Afd'   => [
            '1' => \Convenia\AfdReader\Registry\Afd\Header::class,
            '2' => \Convenia\AfdReader\Registry\Afd\CompanyChange::class,
            '3' => \Convenia\AfdReader\Registry\Afd\Mark::class,
            '4' => \Convenia\AfdReader\Registry\Afd\MarkAdjust::class,
            '5' => \Convenia\AfdReader\Registry\Afd\Employee::class,
        ],
        'Acjef' => [
            '1' => \Convenia\AfdReader\Registry\Acjef\Header::class,
            '2' => \Convenia\AfdReader\Registry\Acjef\ContractualHours::class,
            '3' => \Convenia\AfdReader\Registry\Acjef\Detail::class,
        ],
    ];

    /**
     * AfdReader constructor.
     *
     * @param $filePath
     * @param null $fileType
     *
     * @throws FileNotFoundException
     * @throws WrongFileTypeException
     */
    public function __construct($filePath, $fileType = null)
    {
        $this->fileType = $fileType;
        $this->file = $filePath;
        $this->setFileContents();

        if ($fileType === null) {
            $this->fileType = $this->fileTypeMagic();
        }

        $this->readLines();
    }

    /**
     * check file, if exists set content.
     *
     * @method setFileContents
     */
    private function setFileContents()
    {
        if (file_exists($this->file) === false) {
            throw new FileNotFoundException($this->file);
        }

        $this->fileContents = file($this->file);
    }

    /**
     * Check file type by lines.
     *
     * @throws WrongFileTypeException
     *
     * @return string
     */
    private function fileTypeMagic()
    {
        $trailer = ($this->fileContents[count($this->fileContents) - 2]);
        $trailer = trim($trailer);

        switch (strlen($trailer)) {
            case 34:
                return 'Afd';
            case 55:
                return 'Afdt';
            case 91:
                return 'Acjef';
            default:
                throw new WrongFileTypeException(__METHOD__.' couldn\'t recognize this file.');
        }
    }

    /**
     * Read de Content and transforma in array.
     *
     * @method readLines
     *
     * @return array return array of lines
     */
    private function readLines()
    {
        foreach ($this->fileContents as $value) {
            $this->fileArray[] = $this->translateToArray($value);
        }
    }

    /**
     * Translate line to array info.
     *
     * @method translateToArray
     *
     * @param string $content line
     *
     * @return array line content
     */
    private function translateToArray($content)
    {
        $position = 0;
        $line = [];
        $map = $this->getMap($content);
        if ($map !== false) {
            foreach ($map as $fieldMap) {
                $line[$fieldMap['name']] = substr($content, $position, $fieldMap['size']);
                if (isset($fieldMap['class'])) {
                    $field = new $fieldMap['class']($line[$fieldMap['name']]);
                    $line[$fieldMap['name']] = $field->format($line[$fieldMap['name']]);
                }
                $position = $position + $fieldMap['size'];
            }
        }

        return $line;
    }

    /**
     * Return a map by line type and file type.
     *
     * @method getMap
     *
     * @param [type] $content full line
     *
     * @return array|boll return line map or false
     */
    private function getMap($content)
    {
        $type = $this->getType($content);
        if (isset($this->typeNumber[$this->fileType][$type])) {
            $registry = $this->typeNumber[$this->fileType][$type];
            $class = new $registry();

            return $class->map;
        }

        return false;
    }

    /**
     * Get type line.
     *
     * @method getType
     *
     * @param string $content full line
     *
     * @return string return numeric type of a line
     */
    private function getType($content)
    {
        return substr($content, $this->typePosition, 1);
    }

    /**
     * Return arry by user formated.
     *
     * @method getByUser
     *
     * @return array() By user formated array
     */
    public function getByUser()
    {
        if ($this->fileType == 'Afd') {
            return $this->getByUserAfd();
        } elseif ($this->fileType == 'Afdt') {
            return $this->getByUserAfdt();
        } else {
            return $this->getByUserAcjef();
        }
    }

    /**
     * Get By User on AFD files.
     *
     * @method getByUserAfd
     *
     * @return array() By user formated array
     */
    private function getByUserAfd()
    {
        $userControl = [];
        foreach ($this->fileArray as $value) {
            if ($this->isByUserCondition($value)) {
                if (!isset($userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')])) {
                    $userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')] = 'Entrada';
                    $userControl[$value['identityNumber']]['period'][$value['date']->format('dmY')] = 1;
                }
                $this->userArray[$value['identityNumber']][$value['date']->format('dmY')][$userControl[$value['identityNumber']]['period'][$value['date']->format('dmY')]][] = [
                    'sequency'  => $value['sequency'],
                    'dateTime'  => $value['date']->setTime($value['time']['hour'], $value['time']['minute']),
                    'direction' => $userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')],
                ];

                if ($userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')] == 'Entrada') {
                    $userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')] = 'Saída';
                    continue;
                }

                $userControl[$value['identityNumber']]['direction'][$value['date']->format('dmY')] = 'Entrada';
                $userControl[$value['identityNumber']]['period'][$value['date']->format('dmY')]++;
            }
        }

        return $this->userArray;
    }

    /**
     * Check Line Type on file.
     *
     * @method isByUserCondition
     *
     * @param string $value Full line
     *
     * @return bool If kind of line can be formated to output array
     */
    private function isByUserCondition($value)
    {
        if (!isset($value['type'])) {
            return false;
        }

        if ($this->fileType == 'Afdt' && $value['type'] == 2) {
            return true;
        }

        if ($this->fileType == 'Afd' && $value['type'] == 3) {
            return true;
        }

        if ($this->fileType == 'Acjef' && $value['type'] == 3) {
            return true;
        }

        return false;
    }

    /**
     * Get By User on AFDT files.
     *
     * @method getByUserAfdt
     *
     * @return array() By user formated array
     */
    private function getByUserAfdt()
    {
        foreach ($this->fileArray as $value) {
            if ($this->isByUserCondition($value)) {
                $this->userArray[$value['identityNumber']][$value['clockDate']->format('dmY')][$value['directionOrder']][] = [
                    'sequency'  => $value['sequency'],
                    'dateTime'  => $value['clockDate']->setTime($value['clockTime']['hour'], $value['clockTime']['minute']),
                    'reason'    => $value['reason'],
                    'direction' => $value['direction'],
                    'type'      => $value['registryType'],
                ];
            }
        }

        return $this->userArray;
    }

    /**
     * Get By User on ACJEF files.
     *
     * @method getByUserAcjef
     *
     * @return array() By user formated array
     */
    private function getByUserAcjef()
    {
        foreach ($this->fileArray as $value) {
            if ($this->isByUserCondition($value)) {
                $this->userArray[$value['identityNumber']][] = [
                    'sequency'              => $value['sequency'],
                    'type'                  => $value['type'],
                    'startDate'             => $value['startDate']->format('dmY'),
                    'firstHour'             => $value['firstHour'],
                    'hourCode'              => $value['hourCode'],
                    'hourCode'              => $value['hourCode'],
                    'dayTime'               => $value['dayTime'],
                    'nightTime'             => $value['nightTime'],
                    'overtime1'             => $value['overtime1'],
                    'overtime1'             => $value['overtime1'],
                    'overtimePercentage1'   => $value['overtimePercentage1'],
                    'overtimeModality1'     => $value['overtimeModality1'],
                    'overtime2'             => $value['overtime2'],
                    'overtimePercentage2'   => $value['overtimePercentage2'],
                    'overtimeModality2'     => $value['overtimeModality2'],
                    'overtime3'             => $value['overtime3'],
                    'overtimePercentage3'   => $value['overtimePercentage3'],
                    'overtimeModality3'     => $value['overtimeModality3'],
                    'overtime4'             => $value['overtime4'],
                    'overtimePercentage4'   => $value['overtimePercentage4'],
                    'overtimeModality4'     => $value['overtimeModality4'],
                    'hourAbsencesLate'      => $value['hourAbsencesLate'],
                    'hourSinalCompensate'   => $value['hourSinalCompensate'],
                    'hourBalanceCompensate' => $value['hourBalanceCompensate'],
                ];
            }
        }

        return $this->userArray;
    }
}
