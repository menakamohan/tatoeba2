<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  BEN YAALA Salem <salem.benyaala@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  Tatoeba
 * @author   BEN YAALA Salem <salem.benyaala@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */

App::import('Core', 'Sanitize');

/**
 * Controller for sentence comments.
 *
 * @category Users
 * @package  Controllers
 * @author   BEN YAALA Salem <salem.benyaala@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */
class UserController extends AppController
{

    /**
     * ?
     *
     * @var string
     */
    public $name = 'User';

    /**
     * ?
     *
     * @var array
     */
    public $uses = array(
        'User',
        'Contribution',
        'Sentence',
        'SentenceComment',
        'Favorite'
    );

    /**
     * ?
     *
     * @todo Restrict actions if needed. I don't know much about this stuff.
     *
     * @return void
     */
    public function beforeFilter()
    {
        parent::beforeFilter();

        $this->Auth->allowedActions = array('*');
    }

    /**
     * Display current user's profile.
     *
     * @return void
     */
    public function index()
    {
        $userId = $this->Auth->user('id');
        $aUser = $this->User->getInformationOfCurrentUser($userId);
        $userStats = $this->stats($userId);

        $this->loadModel('Country');
        $aCountries = $this->Country->findAll();

        foreach ($aCountries as $aCountry) {
            $aCleanCountries[$aCountry['Country']['id']]
                = $aCountry['Country']['name'];
        }

        $this->pageTitle = 'Your profile';

        $this->set('countries', $aCleanCountries);
        $this->set('user', $aUser);
        $this->set('userStats', $userStats);
    }


    /**
     * Display profile of given user.
     * If no username is specified, redirect to index
     * (that is current user's profile).
     * If wrong username is specified, redirect to list of members.
     *
     * @param string $sUserName User screen identifiant
     *
     * @return void
     */
    public function profile($sUserName = 'random')
    {
        Sanitize::html($sUserName);

        if ($sUserName == 'random') {
            $aUser = $this->User->find(
                'first',
                array(
                    'conditions' => 'User.group_id < 5',
                    'order' => 'RAND()',
                    'limit' => 1
                )
            );
        } else {

            $aUser = $this->User->getInformationOfUser($sUserName);
        }

        $userStats = $this->stats($aUser['User']['id']);

        $this->set('userStats', $userStats);

        if ( $aUser != null ) {
            if ($aUser['User']['name'] != '') {
                $this->pageTitle
                    = sprintf(__("Profile of %s", true), $aUser['User']['name']);
            } else {
                $this->pageTitle = sprintf(__("%s's profile", true), $sUserName);
            }

            // Check if we can follow that user or not
            // (we can if we're NOT already following the user,
            // or if the user is NOT ourself)
            if ($aUser['User']['id'] != $this->Auth->user('id')) {
                $can_follow = true;
                foreach ($aUser['Follower'] as $follower) {
                    if ($follower['id'] == $this->Auth->user('id')) {
                        $can_follow = false;
                    }
                }
                $this->set('can_follow', $can_follow);
            }

            // Check if his/her profile is public
            $bLogin = $this->Auth->user('id') ? true : false;
            $this->set('login', $bLogin);
            $this->set('is_public', $aUser['User']['is_public']);
            $this->set('user', $aUser);
        } else {
            // TODO better to had message "user %s doesn't exist",
            // but redirect is still better than a strange user's page
            $this->flash(
                sprintf(
                    __('There is no user with this username : %s', true),
                    $sUserName
                ),
                '/users/all'
            );
        }
    }

    /**
     * Save avatar image of current user.
     *
     * @return void
     */
    public function save_image()
    {
        if (!empty($this->data)) {
            if ($this->data['profile_image']['image']['error'] == UPLOAD_ERR_OK) {
                $b = is_uploaded_file(
                    $this->data['profile_image']['image']['tmp_name']
                );
                if ($b) {
                    $sFileExt = strtolower(
                        end(
                            explode(
                                '.',
                                $this->data['profile_image']['image']['name']
                            )
                        )
                    );
                    $aValidExts = array('png', 'jpg', 'jpeg', 'gif');
                    $iFileSize // Size in ko
                        = (int) $this->data['profile_image']['image']['size']/1024;

                    // Check file extension
                    if (!in_array($sFileExt, $aValidExts)) {
                        $this->Session->setFlash(
                            __('Please choose GIF, JPEG or PNG image format.', true)
                        );

                        $this->redirect(array('action' => 'index'));
                    }

                    // Check file size, max 1 mo?
                    if ($iFileSize > 1024) {
                        $this->Session->setFlash(
                            __(
                                'Please choose an image that do not exceed 1 MB.',
                                true
                            )
                        );

                        $this->redirect(array('action' => 'index'));
                    }

                    $this->loadModel('User');

                    $aUser = $this->User->findById($this->Auth->user('id'));

                    $sTmpFile = $this->data['profile_image']['image']['tmp_name'];
                    $sEmail = $aUser['User']['email'];
                    $sProfilesPath = IMAGES . 'profiles' . DS;
                    $sNewFile =  md5($sEmail) . '.' . $sFileExt;

                    if (move_uploaded_file($sTmpFile, $sProfilesPath . $sNewFile)) {
                        $this->User->id = $this->Auth->user('id');
                        $this->User->save(
                            array(
                                'User' => array(
                                    'image' => $sNewFile
                                )
                            )
                        );
                    }
                }
            }
        }

        $this->redirect(array('action' => 'index'));
    }

    /**
     * Save user's description about himself/herself.
     *
     * @return void
     */
    public function save_description()
    {
        if (!empty($this->data)) {

            $aToSave = array();

            if (!empty($this->data['profile_description']['description'])) {
                Sanitize::html($this->data['profile_description']['description']);
                $aToSave += array(
                    'description' =>
                        $this->data['profile_description']['description']
                );
            }

            if (!empty($aToSave)) {
                $this->User->id = $this->Auth->user('id');

                if ($this->User->save(array('User' => $aToSave))) {
                    $this->Session->setFlash(
                        __('Your information have been updated.', true)
                    );
                } else {
                    $this->Session->setFlash(
                        __(
                            'An error occured while saving. Please try again or
                            contact us to report this.',
                            true
                        )
                    );
                }
            }
        }

        $this->redirect(array('action' => 'index'));
    }

    /**
     * Save name, birthday and country
     *
     * @return void
     */
    public function save_basic()
    {
        if (!empty($this->data)) {
            Sanitize::html($this->data['profile_basic']['name']);

            $sBirthday  = $this->data['profile_basic']['birthday']['year'];
            $sBirthday .= '-' . $this->data['profile_basic']['birthday']['month'];
            $sBirthday .= '-' . $this->data['profile_basic']['birthday']['day'];

            $aToSave = array(
                'name' => $this->data['profile_basic']['name'],
                'birthday' => $sBirthday,
                'country_id' => $this->data['profile_basic']['country']
            );

            $this->User->id = $this->Auth->user('id');

            if ($this->User->save(array('User' => $aToSave))) {
                $this->Session->setFlash(
                    __('Your basic information have been updated.', true)
                );
            } else {
                $this->Session->setFlash(
                    __(
                        'An error occured while saving. Please try again or contact
                        us to report this.',
                        true
                    )
                );
            }
        }

        $this->redirect(array('action' => 'index'));
    }


    /**
     * Save email and personal URL.
     *
     * @return void
     */
    public function save_contact()
    {
        if (!empty($this->data)) {
            Sanitize::html($this->data['profile_contact']['description']);
            Sanitize::html($this->data['profile_contact']['email']);

            $aToSave = array(
                'User' => array(
                    'email' => $this->data['profile_contact']['email']
                )
            );

            if (!empty($this->data['profile_contact']['url'])) {
                $aToSave['User']['homepage'] = $this->data['profile_contact']['url'];
            }

            $this->User->id = $this->Auth->user('id');
            if ($this->User->save($aToSave)) {
                $flashMsg = __('Your contact information have been saved.', true);
            } else {
                $flashMsg = __(
                    'An error occured while saving. Please try again or contact us
                    to report this.',
                    true
                );
            }

            $this->Session->setFlash($flashMsg);
        }

        $this->redirect(array('action' => 'index'));
    }

    /**
     * Save option settings. Options are :
     *  - send notification emails
     *  - set profile as public
     *
     * @todo Application language
     *
     * @return void
     */
    public function save_settings()
    {
        if (!empty($this->data)) {

            $aToSave = array(
                'User' => array(
                    'send_notifications' =>
                        $this->data['profile_setting']['send_notifications'],
                    'is_public' => $this->data['profile_setting']['public_profile']
                )
            );

            $this->User->id = $this->Auth->user('id');
            if ($this->User->save($aToSave)) {
                $flashMsg = __('Your settings have been saved.', true);
            } else {
                $flashMsg = __(
                    'An error occured while saving. Please try again or contact us
                    to report this.',
                    true
                );
            }

            $this->Session->setFlash($flashMsg);
        }

        $this->redirect(array('action' => 'index'));
    }

    /**
     * Change password.
     *
     * @return void
     */
    public function save_password()
    {
        if (!empty($this->data)) {

            $this->User->id = $this->Auth->user('id');
            $this->User->recursive = 0;
            $user = $this->User->read();

            $hashedPass = $this->Auth->password(
                $this->data['old_password']['passwd']
            );

            $sNewPassword = $this->data['new_password']['passwd'];

            if ($user['User']['password'] == $hashedPass
                && $sNewPassword == $this->data['new_password2']['passwd']
            ) {

                $this->data['User']['password'] = $this->Auth->password(
                    $sNewPassword
                );

                if ($this->User->save($this->data)) {
                    $flashMsg = __('New password has been saved.', true);
                } else {
                    $flashMsg = __('An error occured while saving.', true);
                }

            } else {

                $flashMsg = __('Password error. Please try again.', true);

            }

            $this->Session->setFlash($flashMsg);
        }

        $this->redirect(array('action' => 'index'));
        /*
         * TODO:
         * change password <- already implemnted, just to move here
         */
    }

    /**
     * Retrieve stats about the user.
     * This is displayed on homepage for now...
     *
     * @param integer $userId User indentifiant
     * 
     * @return array
     */
    public function stats($userId)
    {
        $numberOfSentences = $this->Sentence->numberOfSentencesOwnedBy($userId);
        $numberOfComments
            = $this->SentenceComment->numberOfCommentsOwnedBy($userId);

        $numberOfContributions
            = $this->Contribution->numberOfContributionsBy($userId);
        $numberOfFavorites  = $this->Favorite->numberOfFavoritesOfUser($userId);

        $userStats = array(
            'numberOfComments'      => $numberOfComments ,
            'numberOfSentences'     => $numberOfSentences ,
            'numberOfContributions' => $numberOfContributions,
            'numberOfFavorites'     => $numberOfFavorites
        );
        return $userStats;
    }
}
?>
