<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
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

namespace SP\Repositories\User;

use SP\Core\Exceptions\SPException;
use SP\DataModel\ItemSearchData;
use SP\DataModel\UserData;
use SP\DataModel\UserPreferencesData;
use SP\Repositories\NoSuchItemException;
use SP\Repositories\Repository;
use SP\Repositories\RepositoryItemInterface;
use SP\Repositories\RepositoryItemTrait;
use SP\Services\User\UpdatePassRequest;
use SP\Storage\DbWrapper;
use SP\Storage\QueryData;

/**
 * Class UserRepository
 *
 * @package SP\Repositories\User
 */
class UserRepository extends Repository implements RepositoryItemInterface
{
    use RepositoryItemTrait;

    /**
     * Updates an item
     *
     * @param UserData $itemData
     * @return mixed
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function update($itemData)
    {
        if ($this->checkDuplicatedOnUpdate($itemData)) {
            throw new SPException(__u('Login/email de usuario duplicados'), SPException::INFO);
        }

        $query = /** @lang SQL */
            'UPDATE User SET
            name = ?,
            login = ?,
            ssoLogin = ?,
            email = ?,
            notes = ?,
            userGroupId = ?,
            userProfileId = ?,
            isAdminApp = ?,
            isAdminAcc = ?,
            isDisabled = ?,
            isChangePass = ?,
            isLdap = ?,
            lastUpdate = NOW()
            WHERE id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($itemData->getName());
        $queryData->addParam($itemData->getLogin());
        $queryData->addParam($itemData->getSsoLogin());
        $queryData->addParam($itemData->getEmail());
        $queryData->addParam($itemData->getNotes());
        $queryData->addParam($itemData->getUserGroupId());
        $queryData->addParam($itemData->getUserProfileId());
        $queryData->addParam($itemData->isAdminApp());
        $queryData->addParam($itemData->isAdminAcc());
        $queryData->addParam($itemData->isDisabled());
        $queryData->addParam($itemData->isChangePass());
        $queryData->addParam($itemData->isLdap());
        $queryData->addParam($itemData->getId());
        $queryData->setOnErrorMessage(__u('Error al actualizar el usuario'));

        DbWrapper::getQuery($queryData, $this->db);

        if ($queryData->getQueryNumRows() > 0) {
            $itemData->setId(DbWrapper::getLastId());
        }

        return $this;
    }

    /**
     * Checks whether the item is duplicated on updating
     *
     * @param UserData $itemData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnUpdate($itemData)
    {
        $query = /** @lang SQL */
            'SELECT login, email
            FROM User
            WHERE id <> ? AND (UPPER(login) = UPPER(?) 
            OR (ssoLogin <> "" AND UPPER(ssoLogin) = UPPER(?)) 
            OR UPPER(email) = UPPER(?))';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($itemData->getId());
        $queryData->addParam($itemData->getLogin());
        $queryData->addParam($itemData->getSsoLogin());
        $queryData->addParam($itemData->getEmail());

        DbWrapper::getQuery($queryData, $this->db);

        return $queryData->getQueryNumRows() > 0;
    }

    /**
     * Updates an user's pass
     *
     * @param int               $id
     * @param UpdatePassRequest $passRequest
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updatePassById($id, UpdatePassRequest $passRequest)
    {
        $query = /** @lang SQL */
            'UPDATE User SET
            pass = ?,
            hashSalt = \'\',
            isChangePass = ?,
            isChangedPass = ?,
            isMigrate = 0,
            lastUpdate = NOW()
            WHERE id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($passRequest->getPass());
        $queryData->addParam($passRequest->getisChangePass());
        $queryData->addParam($passRequest->getisChangedPass());
        $queryData->addParam($id);
        $queryData->setOnErrorMessage(__u('Error al modificar la clave'));

        return DbWrapper::getQuery($queryData, $this->db);
    }

    /**
     * Deletes an item
     *
     * @param $id
     * @return int
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function delete($id)
    {
        $queryData = new QueryData();
        $queryData->setQuery('DELETE FROM User WHERE id = ? LIMIT 1');
        $queryData->addParam($id);
        $queryData->setOnErrorMessage(__u('Error al eliminar el usuario'));

        DbWrapper::getQuery($queryData, $this->db);

        return $this->db->getNumRows();
    }

    /**
     * Returns the item for given id
     *
     * @param int $id
     * @return mixed
     * @throws SPException
     */
    public function getById($id)
    {
        $query = /** @lang SQL */
            'SELECT U.id,
            U.name,
            U.userGroupId,
            UG.name AS userGroupName,
            U.login,
            U.ssoLogin,
            U.email,
            U.notes,
            U.loginCount,
            U.userProfileId,
            U.lastLogin,
            U.lastUpdate,
            U.lastUpdateMPass,
            U.preferences,
            U.pass,
            U.hashSalt,
            U.mPass,
            U.mKey,            
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled,
            U.isChangePass,
            U.isChangedPass,
            U.isMigrate
            FROM User U
            INNER JOIN UserGroup UG ON U.userGroupId = UG.id
            WHERE U.id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setMapClassName(UserData::class);
        $queryData->setQuery($query);
        $queryData->addParam($id);

        $queryRes = DbWrapper::getResults($queryData, $this->db);

        if ($queryRes === false) {
            throw new SPException(__u('Error al obtener los datos del usuario'), SPException::ERROR);
        }

        return $queryRes;
    }

    /**
     * Returns all the items
     *
     * @return mixed
     */
    public function getAll()
    {
        $query = /** @lang SQL */
            'SELECT U.id,
            U.name,
            U.userGroupId,
            U.login,
            U.ssoLogin,
            U.email,
            U.notes,
            U.loginCount,
            U.userProfileId,
            U.lastLogin,
            U.lastUpdate,
            U.lastUpdateMPass,
            U.preferences,
            U.pass,
            U.hashSalt,
            U.mPass,
            U.mKey,            
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled,
            U.isChangePass,
            U.isChangedPass,
            U.isMigrate
            FROM User U';

        $queryData = new QueryData();
        $queryData->setMapClassName(UserData::class);
        $queryData->setQuery($query);

        return DbWrapper::getResultsArray($queryData, $this->db);
    }

    /**
     * Returns all the items for given ids
     *
     * @param array $ids
     * @return array
     */
    public function getByIdBatch(array $ids)
    {
        if (count($ids) === 0) {
            return [];
        }

        $query = /** @lang SQL */
            'SELECT U.id,
            U.name,
            U.userGroupId,
            UG.name AS userGroupName,
            U.login,
            U.ssoLogin,
            U.email,
            U.notes,
            U.loginCount,
            U.userProfileId,
            U.lastLogin,
            U.lastUpdate,
            U.lastUpdateMPass,
            U.preferences,
            U.pass,
            U.hashSalt,
            U.mPass,
            U.mKey,            
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled,
            U.isChangePass,
            U.isChangedPass,
            U.isMigrate
            FROM User U
            INNER JOIN UserGroup UG ON U.userGroupId = UG.id
            WHERE U.id IN (' . $this->getParamsFromArray($ids) . ')';

        $queryData = new QueryData();
        $queryData->setMapClassName(UserData::class);
        $queryData->setQuery($query);
        $queryData->setParams($ids);

        return DbWrapper::getResultsArray($queryData, $this->db);
    }

    /**
     * Deletes all the items for given ids
     *
     * @param array $ids
     * @return int
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function deleteByIdBatch(array $ids)
    {
        $queryData = new QueryData();
        $queryData->setQuery('DELETE FROM User WHERE id IN (' . $this->getParamsFromArray($ids) . ')');
        $queryData->setParams($ids);
        $queryData->setOnErrorMessage(__u('Error al eliminar los usuarios'));

        DbWrapper::getQuery($queryData, $this->db);

        return $this->db->getNumRows();
    }

    /**
     * Checks whether the item is in use or not
     *
     * @param $id int
     * @return void
     */
    public function checkInUse($id)
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Searches for items by a given filter
     *
     * @param ItemSearchData $SearchData
     * @return array
     */
    public function search(ItemSearchData $SearchData)
    {
        $queryData = new QueryData();
        $queryData->setSelect('U.id,
            U.name, 
            U.login,
            UP.name AS userProfileName,
            UG.name AS userGroupName,
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled,
            U.isChangePass');
        $queryData->setFrom('User U INNER JOIN UserProfile UP ON U.userProfileId = UP.id INNER JOIN UserGroup UG ON U.userGroupId = UG.id');
        $queryData->setOrder('U.name');

        if ($SearchData->getSeachString() !== '') {
            if ($this->context->getUserData()->getIsAdminApp()) {
                $queryData->setWhere('U.name LIKE ? OR U.login LIKE ?');
            } else {
                $queryData->setWhere('U.name LIKE ? OR U.login LIKE ? AND U.isAdminApp = 0');
            }

            $search = '%' . $SearchData->getSeachString() . '%';
            $queryData->addParam($search);
            $queryData->addParam($search);
        } elseif (!$this->context->getUserData()->getIsAdminApp()) {
            $queryData->setWhere('U.isAdminApp = 0');
        }

        $queryData->setLimit('?, ?');
        $queryData->addParam($SearchData->getLimitStart());
        $queryData->addParam($SearchData->getLimitCount());

        DbWrapper::setFullRowCount();

        $queryRes = DbWrapper::getResultsArray($queryData, $this->db);

        $queryRes['count'] = $queryData->getQueryNumRows();

        return $queryRes;
    }

    /**
     * Creates an item
     *
     * @param UserData $itemData
     * @return int
     * @throws SPException
     */
    public function create($itemData)
    {
        if ($this->checkDuplicatedOnAdd($itemData)) {
            throw new SPException(__u('Login/email de usuario duplicados'), SPException::INFO);
        }

        $query = /** @lang SQL */
            'INSERT INTO User SET
            name = ?,
            login = ?,
            ssoLogin = ?,
            email = ?,
            notes = ?,
            userGroupId = ?,
            userProfileId = ?,
            mPass = ?,
            mKey = ?,
            lastUpdateMPass = ?,
            isAdminApp = ?,
            isAdminAcc = ?,
            isDisabled = ?,
            isChangePass = ?,
            isLdap = ?,
            pass = ?,
            hashSalt = \'\'';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($itemData->getName());
        $queryData->addParam($itemData->getLogin());
        $queryData->addParam($itemData->getSsoLogin());
        $queryData->addParam($itemData->getEmail());
        $queryData->addParam($itemData->getNotes());
        $queryData->addParam($itemData->getUserGroupId());
        $queryData->addParam($itemData->getUserProfileId());
        $queryData->addParam($itemData->getMPass());
        $queryData->addParam($itemData->getMKey());
        $queryData->addParam($itemData->getLastUpdateMPass());
        $queryData->addParam($itemData->isAdminApp());
        $queryData->addParam($itemData->isAdminAcc());
        $queryData->addParam($itemData->isDisabled());
        $queryData->addParam($itemData->isChangePass());
        $queryData->addParam($itemData->isLdap());
        $queryData->addParam($itemData->getPass());
        $queryData->setOnErrorMessage(__u('Error al crear el usuario'));

        DbWrapper::getQuery($queryData, $this->db);

        return $this->db->getLastId();
    }

    /**
     * Checks whether the item is duplicated on adding
     *
     * @param UserData $itemData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnAdd($itemData)
    {
        $query = /** @lang SQL */
            'SELECT login, email
            FROM User
            WHERE UPPER(login) = UPPER(?) 
            OR UPPER(?) IN (SELECT ssoLogin FROM User WHERE ssoLogin IS NOT NULL OR ssoLogin <> \'\')
            OR UPPER(?) IN (SELECT email FROM User WHERE email IS NOT NULL OR email <> \'\')';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($itemData->getLogin());
        $queryData->addParam($itemData->getSsoLogin());
        $queryData->addParam($itemData->getEmail());

        DbWrapper::getQuery($queryData, $this->db);

        return $queryData->getQueryNumRows() > 0;
    }

    /**
     * @param $login string
     * @return UserData
     * @throws SPException
     */
    public function getByLogin($login)
    {
        $query = /** @lang SQL */
            'SELECT U.id,
            U.name,
            U.userGroupId,
            UG.name AS userGroupName,
            U.login,
            U.ssoLogin,
            U.email,
            U.notes,
            U.loginCount,
            U.userProfileId,
            U.lastLogin,
            U.lastUpdate,
            U.lastUpdateMPass,
            U.preferences,
            U.pass,
            U.hashSalt,
            U.mPass,
            U.mKey,            
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled,
            U.isChangePass,
            U.isChangedPass,
            U.isMigrate
            FROM User U
            INNER JOIN UserGroup UG ON U.userGroupId = UG.id
            WHERE U.login = ? OR U.ssoLogin = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setMapClassName(UserData::class);
        $queryData->setQuery($query);
        $queryData->addParam($login);
        $queryData->addParam($login);

        $queryRes = DbWrapper::getResults($queryData, $this->db);

        if ($queryRes === false) {
            throw new SPException(__u('Error al obtener los datos del usuario'), SPException::ERROR);
        }

        if ($queryData->getQueryNumRows() === 0) {
            throw new NoSuchItemException(__u('El usuario no existe'));
        }

        return $queryRes;
    }

    /**
     * Returns items' basic information
     *
     * @return mixed
     */
    public function getBasicInfo()
    {
        $query = /** @lang SQL */
            'SELECT U.id,
            U.name,
            U.login,
            U.email,
            U.userGroupId,
            U.userProfileId,
            U.isAdminApp,
            U.isAdminAcc,
            U.isLdap,
            U.isDisabled
            FROM User U';

        $queryData = new QueryData();
        $queryData->setMapClassName(UserData::class);
        $queryData->setQuery($query);

        return DbWrapper::getResultsArray($queryData, $this->db);
    }

    /**
     * Updates user's master password
     *
     * @param $id
     * @param $pass
     * @param $key
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updateMasterPassById($id, $pass, $key)
    {
        $query = /** @lang SQL */
            'UPDATE User SET 
              mPass = ?,
              mKey = ?,
              lastUpdateMPass = UNIX_TIMESTAMP(),
              isMigrate = 0,
              isChangedPass = 0 
              WHERE id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($pass);
        $queryData->addParam($key);
        $queryData->addParam($id);

        return DbWrapper::getQuery($queryData, $this->db);
    }

    /**
     * Actualiza el último inicio de sesión del usuario en la BBDD.
     *
     * @param $id int El id del usuario
     * @return bool
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function updateLastLoginById($id)
    {
        $queryData = new QueryData();
        $queryData->setQuery('UPDATE User SET lastLogin = NOW(), loginCount = loginCount + 1 WHERE id = ? LIMIT 1');
        $queryData->addParam($id);

        return DbWrapper::getQuery($queryData, $this->db);
    }

    /**
     * @param $login
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkExistsByLogin($login)
    {
        $queryData = new QueryData();
        $queryData->setQuery('SELECT id FROM User WHERE UPPER(login) = UPPER(?) OR UPPER(ssoLogin) = UPPER(?) LIMIT 1');
        $queryData->addParam($login);
        $queryData->addParam($login);

        DbWrapper::getQuery($queryData, $this->db);

        return $queryData->getQueryNumRows() > 0;
    }

    /**
     * @param UserData $itemData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updateOnLogin(UserData $itemData)
    {
        $query = 'UPDATE User SET 
            pass = ?,
            hashSalt = \'\',
            name = ?,
            email = ?,
            lastUpdate = NOW(),
            lastLogin = NOW(),
            isLdap = ? 
            WHERE UPPER(login) = UPPER(?) OR UPPER(ssoLogin) = UPPER(?) LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($itemData->getPass());
        $queryData->addParam($itemData->getName());
        $queryData->addParam($itemData->getEmail());
        $queryData->addParam($itemData->isLdap());
        $queryData->addParam($itemData->getLogin());
        $queryData->addParam($itemData->getLogin());
        $queryData->setOnErrorMessage(__u('Error al actualizar el usuario'));

        return DbWrapper::getQuery($queryData, $this->db);
    }

    /**
     * Updates an user's pass
     *
     * @param int                 $id
     * @param UserPreferencesData $userPreferencesData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updatePreferencesById($id, UserPreferencesData $userPreferencesData)
    {
        $queryData = new QueryData();
        $queryData->setQuery('UPDATE User SET preferences = ? WHERE id = ? LIMIT 1');
        $queryData->addParam(serialize($userPreferencesData));
        $queryData->addParam($id);
        $queryData->setOnErrorMessage(__u('Error al actualizar preferencias\''));

        return DbWrapper::getQuery($queryData, $this->db);
    }

    /**
     * Obtener el email de los usuarios de un grupo
     *
     * @param $groupId
     * @return array
     */
    public function getUserEmailForGroup($groupId)
    {
        $query = /** @lang SQL */
            'SELECT U.id, U.login, U.name, U.email 
            FROM User U
            INNER JOIN UserGroup UG ON U.userGroupId = UG.id
            LEFT JOIN UserToUserGroup UUG ON U.id = UUG.userId
            WHERE U.email IS NOT NULL 
            AND U.userGroupId = ? OR UUG.userGroupId = ?
            AND U.isDisabled = 0
            ORDER BY U.login';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($groupId);
        $queryData->addParam($groupId);

        return DbWrapper::getResultsArray($queryData, $this->db);
    }
}