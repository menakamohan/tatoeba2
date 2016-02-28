<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  HO Ngoc Phuong Trang <tranglich@gmail.com>
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
 * @author   CK
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */

$this->layout = 'light';
?>

<?php
if (isset($sentence)) {

    // display sentence and translations
    $sentences->displaySGroup(
        $sentence['Sentence'],
        $sentence['Translation']
    );
    
    ?>
    <p><i>
    <?php __('Click the top sentence to go to tatoeba.org to translate it or leave a comment.'); ?>
    </i></p>
    <?php
    
} else {
    
    $sentenceId = $this->params['pass'][0];
    echo '<h2>' . format(__('Sentence #{number}', true), array('number' => $sentenceId)) . '</h2>';
    echo '<div class="error">';
        echo format(
            __('There is no sentence with id {number}', true), 
            array('number' => $sentenceId)
        );
    echo '</div>';
    
}
?>

