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

namespace SP\Controller;

use SP\Config\Config;
use SP\Config\ConfigData;
use SP\Core\Exceptions\ValidationException;
use SP\Core\SessionFactory;
use SP\Core\Upgrade\Upgrade;
use SP\Http\JsonResponse;
use SP\Http\Request;
use SP\Log\Log;
use SP\Services\Task\TaskFactory;
use SP\Util\Json;
use SP\Util\Util;

/**
 * Class MainActionController
 *
 * @package SP\Controller
 */
class MainActionController
{
    use SP\Core\Dic\InjectableTrait;

    /**
     * @var ConfigData
     */
    protected $ConfigData;
    /**
     * @var Config
     */
    protected $Config;

    /**
     * MainActionController constructor.
     */
    public function __construct()
    {
        $this->injectDependencies();
    }

    /**
     * @param Config $config
     */
    public function inject(Config $config)
    {
        $this->Config = $config;
        $this->ConfigData = $config->getConfigData();
    }

    /**
     * Realizar acción
     *
     * @param string $version
     * @return bool
     */
    public function doAction($version = '')
    {
        $version = Request::analyze('version', $version);
        $type = Request::analyze('type');
        $taskId = Request::analyze('taskId');

        if (Request::analyze('a') === 'upgrade'
            && Request::analyze('upgrade', 0) === 1
        ) {
            try {
                $JsonResponse = new JsonResponse();
                $JsonResponse->setAction(__('Actualización', false));

                if (Request::analyze('h') !== $this->ConfigData->getUpgradeKey()) {
                    throw new ValidationException(__('Código de seguridad incorrecto', false));
                } elseif (Request::analyze('chkConfirm', false, false, true) === false) {
                    throw new ValidationException(__('Es necesario confirmar la actualización', false));
                }

                TaskFactory::create('upgrade', $taskId);

                $this->upgrade($version, $type);

                $JsonResponse->setDescription(__('Aplicación actualizada correctamente', false));
                $JsonResponse->addMessage(__('En 5 segundos será redirigido al login', false));
                $JsonResponse->setStatus(0);
            } catch (\Exception $e) {
                TaskFactory::end();

                $JsonResponse->setDescription($e->getMessage());
            }

            Json::returnJson($JsonResponse);
        } elseif ($type === 'db' || $type === 'app') {
            $controller = new MainController();
            $controller->getUpgrade($version);
        }

        return false;
    }

    /**
     * Actualizar
     *
     * @param int $version
     * @param int $type
     * @throws \SP\Core\Exceptions\SPException
     */
    private function upgrade($version, $type)
    {
        $Upgrade = new Upgrade();
        $Upgrade->doUpgrade($version);

        TaskFactory::end();

        $this->ConfigData->setMaintenance(false);
        $this->ConfigData->setUpgradeKey('');

        $appVersion = Util::getVersionStringNormalized();

        $this->ConfigData->setConfigVersion($appVersion);

        $this->Config->saveConfig();

        SessionFactory::setAppUpdated();

        $Log = new Log();
        $LogMessage = $Log->getLogMessage();
        $LogMessage->setAction(__('Actualización', false));
        $LogMessage->addDescription(__('Actualización de versión realizada.', false));
        $LogMessage->addDetails(__('Versión', false), sprintf('%d => %d', $version, $appVersion));
        $LogMessage->addDetails(__('Tipo', false), $type);
        $Log->writeLog();
    }
}