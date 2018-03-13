<?php
/**
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Mgmt;

use SP\Config\Config;
use SP\Core\Context\SessionContext;
use SP\Core\DiFactory;
use SP\Core\Exceptions\InvalidClassException;
use SP\Core\Exceptions\SPException;
use SP\DataModel\DataModelInterface;
use SP\Storage\Database;

/**
 * Class ItemBaseTrait
 *
 * @package SP\Mgmt
 */
trait ItemBaseTrait
{
    use SP\Core\Dic\InjectableTrait;

    /**
     * @var string
     */
    protected $dataModel;
    /**
     * @var mixed|DataModelInterface
     */
    protected $itemData;
    /** @var  SessionContext */
    protected $session;

    /**
     * Constructor.
     *
     * @param null $itemData
     * @throws InvalidClassException
     */
    public function __construct($itemData = null)
    {
        $this->injectDependencies();

        $this->init();

        if (null !== $itemData) {
            $this->setItemData($itemData);
        } else {
            $this->itemData = new $this->dataModel();
        }
    }

    /**
     * Devolver la instancia almacenada de la clase. Si no existe, se crea
     *
     * @param $itemData
     * @return static
     */
    final public static function getItem($itemData = null)
    {
        return DiFactory::getItem(static::class, $itemData);
    }

    /**
     * Devolver una nueva instancia de la clase
     *
     * @param null $itemData
     * @return static
     * @throws \SP\Core\Exceptions\InvalidClassException
     */
    final public static function getNewItem($itemData = null)
    {
        return new static($itemData);
    }

    /**
     * Devolver los datos del elemento
     *
     * @return mixed|DataModelInterface
     */
    public function getItemData()
    {
        return is_object($this->itemData) ? $this->itemData : new $this->dataModel();
    }

    /**
     * @param $itemData
     * @return $this
     * @throws InvalidClassException
     */
    final public function setItemData($itemData)
    {
        if (null !== $this->dataModel && ($itemData instanceof $this->dataModel) === false) {
            throw new InvalidClassException(SPException::ERROR, $this->dataModel);
        }

        $this->itemData = $itemData;

        return $this;
    }

    /**
     * @return string
     */
    public function getDataModel()
    {
        return $this->dataModel;
    }

    /**
     * @param string $dataModel
     * @return static
     * @throws InvalidClassException
     */
    final protected function setDataModel($dataModel)
    {
        if (false === class_exists($dataModel)) {
            throw new InvalidClassException(SPException::ERROR, $dataModel);
        }

        $this->dataModel = $dataModel;

        return $this;
    }

    /**
     * @param Config   $config
     * @param Database $db
     * @param SessionContext  $session
     */
    public function inject(Config $config, Database $db, SessionContext $session)
    {
        $this->ConfigData = $config->getConfigData();
        $this->db = $db;
        $this->session = $session;
    }

    /**
     * Inicializar la clase
     *
     * @return void
     */
    abstract protected function init();
}