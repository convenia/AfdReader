<?php
/**
* Classe para validação de Arquivo Fonte de Dados (AFD).
*
* Especificação do MTE referente a portaria 1.510/2009, especifica um padrão
* para os arquivos gerados pelos REP - Registrador Eletrônico de Ponto
*
* @author  Victor Ventura <euventura@gmail.com>
*
* @version  0.1
*
* @copyright  CC BY-SA 3.0 <http://creativecommons.org/licenses/by-sa/3.0/>
*
* @todo  Ler Arquivos AFD e AFDF e devolver em Array
*/
namespace Convenia\AfdReader\Field;

use Convenia\AfdReader\Exception\RegistryNotExistsException;
use Convenia\AfdReader\Interfaces\FieldInterface;

class RegistryType implements FieldInterface
{
    private $types = [
        'O' => 'Original',
        'I' => 'Incluído por digitação',
        'P' => 'Pré-assinalado',
    ];

    /**
     * Format field type.
     *
     * @method format
     *
     * @param string $value value to be formated
     *
     * @return string formated value
     */
    public function format($value)
    {
        if (isset($this->types[$value])) {
            return $this->types[$value];
        }
        throw new RegistryNotExistsException($value);
    }
}
