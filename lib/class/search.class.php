<?php
declare(strict_types=0);
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPLv3)
 * Copyright 2001 - 2020 Ampache.org
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
 */

use Lib\Metadata\Repository\MetadataField;

/**
 * Search Class
 * Search-related voodoo.  Beware tentacles.
 */

class Search extends playlist_object
{
    public $searchtype;
    public $rules;
    public $logic_operator = 'AND';
    public $type           = 'public';
    public $random         = 0;
    public $limit          = 0;
    public $last_count     = 0;

    public $operators;
    public $searchfields;

    public $link;
    public $f_link;

    public $search_user;

    /**
     * constructor
     * @param integer $search_id
     * @param string $searchtype
     * @param User $user
     */
    public function __construct($search_id = 0, $searchtype = 'song', $user = null)
    {
        if ($user) {
            $this->search_user = $user;
        } else {
            $this->search_user = Core::get_global('user');
        }
        $this->searchtype = $searchtype;
        if ($search_id > 0) {
            $info = $this->get_info($search_id);
            foreach ($info as $key => $value) {
                $this->$key = $value;
            }
            $this->rules = json_decode((string) $this->rules, true);
        }

        // Define our operators
        $this->populate_operators();

        $this->searchfields = array();
        switch ($searchtype) {
            case 'song':
                $this->populate_song_fields();
                break;
            case 'album':
                $this->populate_album_fields();
                break;
            case 'video':
                $this->populate_video_fields();
                break;
            case 'artist':
                $this->populate_artist_fields();
                break;
            case 'playlist':
                $this->populate_playlist_fields();
                break;
            case 'label':
                $this->populate_label_fields();
                break;
            case 'user':
                $this->populate_user_fields();
                break;
        } // end switch on searchtype
    } // end constructor

    /**
     * populate_operators
     *
     * Function called during construction to set the different search operators for each field type
     */
    private function populate_operators()
    {
        $this->operators['numeric'][] = array(
            'name' => 'gte',
            'description' => T_('is greater than or equal to'),
            'sql' => '>='
        );

        $this->operators['numeric'][] = array(
            'name' => 'lte',
            'description' => T_('is less than or equal to'),
            'sql' => '<='
        );

        $this->operators['numeric'][] = array(
            'name' => 'equal',
            'description' => T_('is'),
            'sql' => '<=>'
        );

        $this->operators['numeric'][] = array(
            'name' => 'ne',
            'description' => T_('is not'),
            'sql' => '<>'
        );

        $this->operators['numeric'][] = array(
            'name' => 'gt',
            'description' => T_('is greater than'),
            'sql' => '>'
        );

        $this->operators['numeric'][] = array(
            'name' => 'lt',
            'description' => T_('is less than'),
            'sql' => '<'
        );

        $this->operators['boolean'][] = array(
            'name' => 'true',
            'description' => T_('is true'),
            'sql' => '1'
        );

        $this->operators['boolean'][] = array(
            'name' => 'false',
            'description' => T_('is false'),
            'sql' => '0'
        );

        $this->operators['text'][] = array(
            'name' => 'contain',
            'description' => T_('contains'),
            'sql' => 'LIKE',
            'preg_match' => array('/^/', '/$/'),
            'preg_replace' => array('%', '%')        );

        $this->operators['text'][] = array(
            'name' => 'notcontain',
            'description' => T_('does not contain'),
            'sql' => 'NOT LIKE',
            'preg_match' => array('/^/', '/$/'),
            'preg_replace' => array('%', '%')
        );

        $this->operators['text'][] = array(
            'name' => 'start',
            'description' => T_('starts with'),
            'sql' => 'LIKE',
            'preg_match' => '/$/',
            'preg_replace' => '%'
        );

        $this->operators['text'][] = array(
            'name' => 'end',
            'description' => T_('ends with'),
            'sql' => 'LIKE',
            'preg_match' => '/^/',
            'preg_replace' => '%'
        );

        $this->operators['text'][] = array(
            'name' => 'equal',
            'description' => T_('is'),
            'sql' => '='
        );

        $this->operators['text'][] = array(
            'name' => 'not equal',
            'description' => T_('is not'),
            'sql' => '!='
        );

        $this->operators['text'][] = array(
            'name' => 'sounds',
            'description' => T_('sounds like'),
            'sql' => 'SOUNDS LIKE'
        );

        $this->operators['text'][] = array(
            'name' => 'notsounds',
            'description' => T_('does not sound like'),
            'sql' => 'NOT SOUNDS LIKE'
        );
        $this->operators['tags'][] = array(
            'name' => 'contain',
            'description' => T_('contains'),
            'sql' => 'LIKE',
            'preg_match' => array('/^/', '/$/'),
            'preg_replace' => array('%', '%')
        );

        $this->operators['tags'][] = array(
            'name' => 'notcontain',
            'description' => T_('does not contain'),
            'sql' => 'NOT LIKE',
            'preg_match' => array('/^/', '/$/'),
            'preg_replace' => array('%', '%')
        );

        $this->operators['tags'][] = array(
            'name' => 'start',
            'description' => T_('starts with'),
            'sql' => 'LIKE',
            'preg_match' => '/$/',
            'preg_replace' => '%'
        );

        $this->operators['tags'][] = array(
            'name' => 'end',
            'description' => T_('ends with'),
            'sql' => 'LIKE',
            'preg_match' => '/^/',
            'preg_replace' => '%'
        );

        $this->operators['tags'][] = array(
            'name' => 'equal',
            'description' => T_('is'),
            'sql' => '>'
        );

        $this->operators['tags'][] = array(
            'name' => 'not equal',
            'description' => T_('is not'),
            'sql' => '='
        );

        $this->operators['boolean_numeric'][] = array(
            'name' => 'equal',
            'description' => T_('is'),
            'sql' => '<=>'
        );

        $this->operators['boolean_numeric'][] = array(
            'name' => 'ne',
            'description' => T_('is not'),
            'sql' => '<>'
        );

        $this->operators['boolean_subsearch'][] = array(
            'name' => 'equal',
            'description' => T_('is'),
            'sql' => ''
        );

        $this->operators['boolean_subsearch'][] = array(
            'name' => 'ne',
            'description' => T_('is not'),
            'sql' => 'NOT'
        );

        $this->operators['date'][] = array(
            'name' => 'lt',
            'description' => T_('before'),
            'sql' => '<'
        );

        $this->operators['date'][] = array(
            'name' => 'gt',
            'description' => T_('after'),
            'sql' => '>'
        );

        $this->operators['days'][] = array(
            'name' => 'lt',
            'description' => T_('before (x) days ago'),
            'sql' => '<'
        );

        $this->operators['days'][] = array(
            'name' => 'gt',
            'description' => T_('after (x) days ago'),
            'sql' => '>'
        );

        $this->operators['recent_added'][] = array(
            'name' => 'add',
            'description' => T_('# songs'),
            'sql' => '`addition_time`'
        );

        $this->operators['recent_updated'][] = array(
            'name' => 'upd',
            'description' => T_('# songs'),
            'sql' => '`update_time`'
        );

        $this->operators['user_numeric'][] = array(
            'name' => 'love',
            'description' => T_('has loved'),
            'sql' => 'userflag'
        );

        $this->operators['user_numeric'][] = array(
            'name' => '5star',
            'description' => T_('has rated 5 stars'),
            'sql' => '`rating`.`rating` = 5'
        );

        $this->operators['user_numeric'][] = array(
            'name' => '4star',
            'description' => T_('has rated 4 stars'),
            'sql' => '`rating`.`rating` = 4'
        );

        $this->operators['user_numeric'][] = array(
            'name' => '3star',
            'description' => T_('has rated 3 stars'),
            'sql' => '`rating`.`rating` = 3'
        );

        $this->operators['user_numeric'][] = array(
            'name' => '2star',
            'description' => T_('has rated 2 stars'),
            'sql' => '`rating`.`rating` = 2'
        );

        $this->operators['user_numeric'][] = array(
            'name' => '1star',
            'description' => T_('has rated 1 star'),
            'sql' => '`rating`.`rating` = 1'
        );

        $this->operators['multiple'] = array_merge($this->operators['text'], $this->operators['numeric']);
    }

    /**
     * popuplate_time_field
     *
     * Length (in minutes) Numeric search (Song, Album)
     */
    private function popuplate_time_field()
    {
        $this->searchfields[] = array(
            'name' => 'time',
            'label' => T_('Length (in minutes)'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );
    }

    /**
     * populate_artistrating_field
     *
     * My Rating (Artist) Numeric search (Song, Album)
     */
    private function populate_artistrating_field()
    {
        $this->searchfields[] = array(
            'name' => 'artistrating',
            'label' => T_('My Rating (Artist)'),
            'type' => 'numeric',
            'widget' => array(
                'select',
                array(
                    '1 Star',
                    '2 Stars',
                    '3 Stars',
                    '4 Stars',
                    '5 Stars'
                )
            )
        );
    }

    /**
     * populate_albumrating_field
     *
     * My Rating (Album) Numeric search (Song)
     */
    private function populate_albumrating_field()
    {
        $this->searchfields[] = array(
            'name' => 'albumrating',
            'label' => T_('My Rating (Album)'),
            'type' => 'numeric',
            'widget' => array(
                'select',
                array(
                    '1 Star',
                    '2 Stars',
                    '3 Stars',
                    '4 Stars',
                    '5 Stars'
                )
            )
        );
    }

    /**
     * populate_image_height_field
     *
     * Image Height (Album, Artist)
     */
    private function populate_image_height_field()
    {
        $this->searchfields[] = array(
            'name' => 'image height',
            'label' => T_('Image Height'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );
    }

    /**
     * populate_image_width_field
     *
     * Image Width (Album, Artist)
     */
    private function populate_image_width_field()
    {
        $this->searchfields[] = array(
            'name' => 'image width',
            'label' => T_('Image Width'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );
    }

    /**
     * populate_last_play_field
     *
     * My Last Play in days (song, album, artist)
     */
    private function populate_last_play_field()
    {
        $this->searchfields[] = array(
            'name' => 'last_play',
            'label' => T_('My Last Play'),
            'type' => 'days',
            'widget' => array('input', 'number')
        );
    }

    /**
     * populate_rating_field
     *
     * Rating (Average) across all users (song, album, artist)
     */
    private function populate_rating_field()
    {
        $this->searchfields[] = array(
            'name' => 'rating',
            'label' => T_('Rating (Average)'),
            'type' => 'numeric',
            'widget' => array(
                'select',
                array(
                    '1 Star',
                    '2 Stars',
                    '3 Stars',
                    '4 Stars',
                    '5 Stars'
                )
            )
        );
    }

    /**
     * populate_myrating_field
     *
     * My Rating, the rating from your user (song, album, artist)
     */
    private function populate_myrating_field()
    {
        $this->searchfields[] = array(
            'name' => 'myrating',
            'label' => T_('My Rating'),
            'type' => 'numeric',
            'widget' => array(
                'select',
                array(
                    '1 Star',
                    '2 Stars',
                    '3 Stars',
                    '4 Stars',
                    '5 Stars'
                )
            )
        );
    }

    /**
     * populate_played_times_field
     *
     * # Played, Number of times this object has been played (song, album, artist)
     */
    private function populate_played_times_field()
    {
        $this->searchfields[] = array(
            'name' => 'played_times',
            /* HINT: Number of times object has been played */
            'label' => T_('# Played'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );
    }

    /**
     * populate_favorite_field
     *
     * Objects you have flagged / loved (song, album, artist)
     */
    private function populate_favorite_field()
    {
        $this->searchfields[] = array(
            'name' => 'favorite',
            'label' => T_('Favorites'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
    }

    /**
     * populate_song_fields
     *
     * this is where all the searchtypes for songs are defined
     */
    private function populate_song_fields()
    {
        $this->searchfields[] = array(
            'name' => 'anywhere',
            'label' => T_('Any searchable text'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'title',
            'label' => T_('Title'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'album',
            'label' => T_('Album'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'artist',
            'label' => T_('Artist'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'composer',
            'label' => T_('Composer'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'year',
            'label' => T_('Year'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );

        if (AmpConfig::get('ratings')) {
            $this->populate_myrating_field();
            $this->populate_rating_field();
            $this->populate_albumrating_field();
            $this->populate_artistrating_field();
        }

        $this->searchfields[] = array(
            'name' => 'tag',
            'label' => T_('Tag'),
            'type' => 'tags',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'album_tag',
            'label' => T_('Album tag'),
            'type' => 'tags',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'artist_tag',
            'label' => T_('Artist tag'),
            'type' => 'tags',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'file',
            'label' => T_('Filename'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->popuplate_time_field();

        if (AmpConfig::get('userflags')) {
            $this->populate_favorite_field();
        }

        $this->populate_played_times_field();

        $this->searchfields[] = array(
            'name' => 'comment',
            'label' => T_('Comment'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'label',
            'label' => T_('Label'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );


        $this->searchfields[] = array(
            'name' => 'bitrate',
            'label' => T_('Bitrate'),
            'type' => 'numeric',
            'widget' => array(
                'select',
                array(
                    '32',
                    '40',
                    '48',
                    '56',
                    '64',
                    '80',
                    '96',
                    '112',
                    '128',
                    '160',
                    '192',
                    '224',
                    '256',
                    '320'
                )
            )
        );

        $this->populate_last_play_field();

        $this->searchfields[] = array(
            'name' => 'played',
            'label' => T_('Played'),
            'type' => 'boolean',
            'widget' => array('input', 'hidden')
        );

        $this->searchfields[] = array(
            'name' => 'myplayed',
            'label' => T_('Played by Me'),
            'type' => 'boolean',
            'widget' => array('input', 'hidden')
        );

        $this->searchfields[] = array(
            'name' => 'myplayedalbum',
            'label' => T_('Played by Me (Album)'),
            'type' => 'boolean',
            'widget' => array('input', 'hidden')
        );

        $this->searchfields[] = array(
            'name' => 'myplayedartist',
            'label' => T_('Played by Me (Artist)'),
            'type' => 'boolean',
            'widget' => array('input', 'hidden')
        );

        $this->searchfields[] = array(
            'name' => 'added',
            'label' => T_('Added'),
            'type' => 'date',
            'widget' => array('input', 'datetime-local')
        );

        $this->searchfields[] = array(
            'name' => 'updated',
            'label' => T_('Updated'),
            'type' => 'date',
            'widget' => array('input', 'datetime-local')
        );

        $this->fields[] = array(
            'name' => 'recent_added',
            'label' => T_('Recently added'),
            'type' => 'recent_added',
            'widget' => array('input', 'number')
        );

        $this->fields[] = array(
            'name' => 'recent_updated',
            'label' => T_('Recently updated'),
            'type' => 'recent_updated',
            'widget' => array('input', 'number')
        );

        $catalogs = array();
        foreach (Catalog::get_catalogs() as $catid) {
            $catalog = Catalog::create_from_id($catid);
            $catalog->format();
            $catalogs[$catid] = $catalog->f_name;
        }
        $this->searchfields[] = array(
            'name' => 'catalog',
            'label' => T_('Catalog'),
            'type' => 'boolean_numeric',
            'widget' => array('select', $catalogs)
        );

        $playlists = array();
        foreach (Playlist::get_playlists() as $playlistid) {
            $playlist = new Playlist($playlistid);
            $playlist->format(false);
            $playlists[$playlistid] = $playlist->f_name;
        }
        $this->searchfields[] = array(
            'name' => 'playlist',
            'label' => T_('Playlist'),
            'type' => 'boolean_numeric',
            'widget' => array('select', $playlists)
        );

        $users = array();
        foreach (User::get_valid_users() as $userid) {
            $user           = new User($userid);
            $users[$userid] = $user->username;
        }
        $this->searchfields[] = array(
            'name' => 'other_user',
            'label' => T_('Another User'),
            'type' => 'user_numeric',
            'widget' => array('select', $users)
        );
        $this->searchfields[] = array(
            'name' => 'other_user_album',
            'label' => T_('Another User (Album)'),
            'type' => 'user_numeric',
            'widget' => array('select', $users)
        );
        $this->searchfields[] = array(
            'name' => 'other_user_artist',
            'label' => T_('Another User (Artist)'),
            'type' => 'user_numeric',
            'widget' => array('select', $users)
        );

        $this->searchfields[] = array(
            'name' => 'playlist_name',
            'label' => T_('Playlist Name'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $playlists = array();
        $searches  = Search::get_searches();
        foreach ($searches as $playlistid) {
            // Slightly different from the above so we don't instigate
            // a vicious loop.
            $playlists[$playlistid] = Search::get_name_byid($playlistid);
        }
        $this->searchfields[] = array(
            'name' => 'smartplaylist',
            'label' => T_('Smart Playlist'),
            'type' => 'boolean_subsearch',
            'widget' => array('select', $playlists)
        );

        $metadataFields          = array();
        $metadataFieldRepository = new MetadataField();
        foreach ($metadataFieldRepository->findAll() as $metadata) {
            $metadataFields[$metadata->getId()] = $metadata->getName();
        }
        $this->searchfields[] = array(
            'name' => 'metadata',
            'label' => T_('Metadata'),
            'type' => 'multiple',
            'subtypes' => $metadataFields,
            'widget' => array('subtypes', array('input', 'text'))
        );

        $licenses = array();
        foreach (License::get_licenses() as $license_id) {
            $license               = new License($license_id);
            $licenses[$license_id] = $license->name;
        }
        if (AmpConfig::get('licensing')) {
            $this->searchfields[] = array(
                'name' => 'license',
                'label' => T_('Music License'),
                'type' => 'boolean_numeric',
                'widget' => array('select', $licenses)
            );
        }
    }

    /**
     * populate_artist_fields
     *
     * this is where all the searchtypes for artists are defined
     */
    private function populate_artist_fields()
    {
        $this->searchfields[] = array(
            'name' => 'title',
            'label' => T_('Name'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
        $this->searchfields[] = array(
            'name' => 'yearformed',
            'label' => T_('Year'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );
        if (AmpConfig::get('ratings')) {
            $this->populate_myrating_field();
            $this->populate_rating_field();
        }
        $this->searchfields[] = array(
            'name' => 'placeformed',
            'label' => T_('Place'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
        $this->searchfields[] = array(
            'name' => 'tag',
            'label' => T_('Tag'),
            'type' => 'tags',
            'widget' => array('input', 'text')
        );

        if (AmpConfig::get('userflags')) {
            $this->populate_favorite_field();
        }

        $users = array();
        foreach (User::get_valid_users() as $userid) {
            $user           = new User($userid);
            $users[$userid] = $user->username;
        }
        $this->searchfields[] = array(
            'name' => 'other_user',
            'label' => T_('Another User'),
            'type' => 'user_numeric',
            'widget' => array('select', $users)
        );

        $this->populate_last_play_field();
        $this->popuplate_time_field();
        $this->populate_played_times_field();
        $this->populate_image_width_field();
        $this->populate_image_height_field();
    }

    /**
     * populate_album_fields
     *
     * this is where all the searchtypes for albums are defined
     */
    private function populate_album_fields()
    {
        $this->searchfields[] = array(
            'name' => 'title',
            'label' => T_('Title'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'artist',
            'label' => T_('Artist'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );

        $this->searchfields[] = array(
            'name' => 'year',
            'label' => T_('Year'),
            'type' => 'numeric',
            'widget' => array('input', 'number')
        );

        if (AmpConfig::get('ratings')) {
            $this->populate_myrating_field();
            $this->populate_rating_field();
            $this->populate_artistrating_field();
        }
        $this->populate_played_times_field();
        $this->populate_last_play_field();
        $this->popuplate_time_field();

        if (AmpConfig::get('userflags')) {
            $this->populate_favorite_field();
        }

        $users = array();
        foreach (User::get_valid_users() as $userid) {
            $user           = new User($userid);
            $users[$userid] = $user->username;
        }
        $this->searchfields[] = array(
            'name' => 'other_user',
            'label' => T_('Another User'),
            'type' => 'user_numeric',
            'widget' => array('select', $users)
        );

        $this->searchfields[] = array(
            'name' => 'tag',
            'label' => T_('Tag'),
            'type' => 'tags',
            'widget' => array('input', 'text')
        );

        $catalogs = array();
        foreach (Catalog::get_catalogs() as $catid) {
            $catalog = Catalog::create_from_id($catid);
            $catalog->format();
            $catalogs[$catid] = $catalog->f_name;
        }
        $this->searchfields[] = array(
            'name' => 'catalog',
            'label' => T_('Catalog'),
            'type' => 'boolean_numeric',
            'widget' => array('select', $catalogs)
        );

        $this->populate_image_width_field();
        $this->populate_image_height_field();
    }

    /**
     * populate_video_fields
     *
     * this is where all the searchtypes for videos are defined
     */
    private function populate_video_fields()
    {
        $this->searchfields[] = array(
            'name' => 'filename',
            'label' => T_('Filename'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
    }

    /**
     * populate_playlist_fields
     *
     * this is where all the searchtypes for playlists are defined
     */
    private function populate_playlist_fields()
    {
        $this->searchfields[] = array(
            'name' => 'title',
            'label' => T_('Name'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
    }

    /**
     * populate_label_fields
     *
     * this is where all the searchtypes for labels are defined
     */
    private function populate_label_fields()
    {
        $this->searchfields[] = array(
            'name' => 'title',
            'label' => T_('Name'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
        $this->searchfields[] = array(
            'name' => 'category',
            'label' => T_('Category'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
    }

    /**
     * populate_user_fields
     *
     * this is where all the searchtypes for users are defined
     */
    private function populate_user_fields()
    {
        $this->searchfields[] = array(
            'name' => 'username',
            'label' => T_('Username'),
            'type' => 'text',
            'widget' => array('input', 'text')
        );
    }

    /**
     * clean_request
     *
     * Sanitizes raw search data
     * @param array $data
     * @return array
     */
    public static function clean_request($data)
    {
        $request = array();
        foreach ($data as $key => $value) {
            $prefix = substr($key, 0, 4);
            $value  = trim((string) $value);

            if ($prefix == 'rule' && strlen((string) $value)) {
                $request[$key] = Dba::escape(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
            }
        }

        // Figure out if they want an AND based search or an OR based search
        switch ($data['operator']) {
            case 'or':
                $request['operator'] = 'OR';
            break;
            default:
                $request['operator'] = 'AND';
            break;
        }

        // Verify the type
        switch ($data['type']) {
            case 'album':
            case 'artist':
            case 'video':
            case 'song':
            case 'playlist':
            case 'label':
            case 'user':
                $request['type'] = $data['type'];
            break;
            default:
                $request['type'] = 'song';
            break;
        }

        return $request;
    } // end clean_request

    /**
     * get_name_byid
     *
     * Returns the name of the saved search corresponding to the given ID
     * @param string $search_id
     * @return string
     */
    public static function get_name_byid($search_id)
    {
        $sql        = "SELECT `name` FROM `search` WHERE `id` = '$search_id'";
        $db_results = Dba::read($sql);
        $row        = Dba::fetch_assoc($db_results);

        return $row['name'];
    }

    /**
     * get_searches
     *
     * Return the IDs of all saved searches accessible by the current user.
     * @return array
     */
    public static function get_searches()
    {
        $sql = "SELECT `id` from `search` WHERE `type`='public' OR " .
            "`user`='" . Core::get_global('user')->id . "' ORDER BY `name`";
        $db_results = Dba::read($sql);

        $results = array();

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;
    }

    /**
     * run
     *
     * This function actually runs the search and returns an array of the
     * results.
     * @param array $data
     * @param User $user
     * @return array
     */
    public static function run($data, $user = null)
    {
        $limit  = (int) ($data['limit']);
        $offset = (int) ($data['offset']);
        $data   = self::clean_request($data);

        $search = new Search(null, $data['type'], $user);
        $search->parse_rules($data);

        // Generate BASE SQL

        $limit_sql = "";
        if ($limit > 0) {
            $limit_sql = ' LIMIT ';
            if ($offset) {
                $limit_sql .= $offset . ",";
            }
            $limit_sql .= $limit;
        }

        $search_info = $search->to_sql();
        $sql         = $search_info['base'] . ' ' . $search_info['table_sql'];
        if (!empty($search_info['where_sql'])) {
            $sql .= ' WHERE ' . $search_info['where_sql'];
        }
        if (!empty($search_info['group_sql'])) {
            $sql .= ' GROUP BY ' . $search_info['group_sql'];
            if (!empty($search_info['having_sql'])) {
                $sql .= ' HAVING ' . $search_info['having_sql'];
            }
        }
        $sql .= ' ' . $limit_sql;
        $sql = trim((string) $sql);

        //debug_event('search.class', 'SQL get_items: ' . $sql, 5);
        $db_results = Dba::read($sql);
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;
    }

    /**
     * delete
     *
     * Does what it says on the tin.
     * @return boolean
     */
    public function delete()
    {
        $search_id = Dba::escape($this->id);
        $sql       = "DELETE FROM `search` WHERE `id` = ?";
        Dba::write($sql, array($search_id));

        return true;
    }

    /**
     * format
     * Gussy up the data
     * @param boolean $details
     */
    public function format($details = true)
    {
        parent::format($details);

        $this->link   = AmpConfig::get('web_path') . '/smartplaylist.php?action=show_playlist&playlist_id=' . $this->id;
        $this->f_link = '<a href="' . $this->link . '">' . $this->f_name . '</a>';
    }

    /**
     * get_items
     *
     * Return an array of the items output by our search (part of the
     * playlist interface).
     * @return array
     */
    public function get_items()
    {
        $results = array();

        $sqltbl = $this->to_sql();
        $sql    = $sqltbl['base'] . ' ' . $sqltbl['table_sql'];
        if (!empty($sqltbl['where_sql'])) {
            $sql .= ' WHERE ' . $sqltbl['where_sql'];
        }
        if (!empty($sqltbl['group_sql'])) {
            $sql .= ' GROUP BY ' . $sqltbl['group_sql'];
        }
        if (!empty($sqltbl['having_sql'])) {
            $sql .= ' HAVING ' . $sqltbl['having_sql'];
        }

        if ($this->random > 0) {
            $sql .= " ORDER BY RAND()";
        }
        if ($this->limit > 0) {
            $sql .= " LIMIT " . (string) ($this->limit);
        }
        //debug_event('search.class', 'SQL get_items: ' . $sql, 5);

        $db_results = Dba::read($sql);
        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = array(
                'object_id' => $row['id'],
                'object_type' => $this->searchtype
            );
        }
        $this->set_last_count(count($results));

        return $results;
    }

    /**
     * set_last_count
     *
     * Updates the number of results for the last search in the database
     * @param integer $count
     */
    private function set_last_count($count)
    {
        $search_id = Dba::escape($this->id);
        $sql       = "Update `search` SET `last_count` = " . $count . " WHERE `id` = ?";
        Dba::write($sql, array($search_id));
    }

    /**
     * get_random_items
     *
     * Returns a randomly sorted array (with an optional limit) of the items
     * output by our search (part of the playlist interface)
     * @param integer $limit
     * @return array
     */
    public function get_random_items($limit = null)
    {
        $results = array();

        $sqltbl = $this->to_sql();
        $sql    = $sqltbl['base'] . ' ' . $sqltbl['table_sql'];
        if (!empty($sqltbl['where_sql'])) {
            $sql .= ' WHERE ' . $sqltbl['where_sql'];
        }
        $rating_filter = AmpConfig::get_rating_filter();
        if ($rating_filter > 0 && $rating_filter <= 5 && Core::get_global('user')) {
            $user_id = Core::get_global('user')->id;
            if (empty($sqltbl['where_sql'])) {
                $sql .= " WHERE ";
            } else {
                $sql .= " AND ";
            }
            $sql .= "`" . $this->searchtype . "`.`id` NOT IN" .
                    " (SELECT `object_id` FROM `rating`" .
                    " WHERE `rating`.`object_type` = '" . $this->searchtype . "'" .
                    " AND `rating`.`rating` <=" . $rating_filter .
                    " AND `rating`.`user` = " . $user_id . ")";
        }
        if (!empty($sqltbl['group_sql'])) {
            $sql .= ' GROUP BY ' . $sqltbl['group_sql'];
        }
        if (!empty($sqltbl['having_sql'])) {
            $sql .= ' HAVING ' . $sqltbl['having_sql'];
        }

        $sql .= ' ORDER BY RAND()';
        $sql .= $limit ? ' LIMIT ' . (string) ($limit) : '';
        //debug_event('search.sql', 'SQL get_random_items: ' . $sql, 5);

        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = array(
                'object_id' => $row['id'],
                'object_type' => $this->searchtype
            );
        }

        return $results;
    }

    /**
     * get_searchfield_datatype
     *
     * Iterates over our array of search fields to find out the data type for
     * the passed string.
     * @param $fieldname
     * @return string|false
     */
    public function get_searchfield_datatype($fieldname)
    {
        foreach ($this->searchfields as $field) {
            if ($field['name'] == $fieldname) {
                return $field['type'];
            }
        }

        return false;
    }

    /**
     * get_operator_index_by_name
     *
     * Gives the array index of the search operator for the given type and operator name.
     * @param $type
     * @param $name
     * @return int|false
     */
    private function get_operator_index_by_name($type, $name)
    {
        foreach ($this->operators[$type] as $key => $operator) {
            if ($operator['name'] == $name) {
                return $key;
            }
        }
        return false;
    }

    /**
     * get_operator_by_name
     *
     * Return an array with the definition of the search operator for the given type and operator name.
     * @param $type
     * @param $name
     * @return array
     */
    private function get_operator_array_by_name($type, $name)
    {
        $index = $this->get_operator_index_by_name($type, $name);
        if ($index !== false) return $this->operators[$type][$index];
        else return array();
    }

    /**
     * add_rule
     *
     * Add rule to rules array
     * @param string $search_field
     * @param string $operator_name
     * @param string $value
     * @param string $subtype
     */
    private function add_rule($search_field, $operator_name, $value, $subtype=null)
    {
        $this->rules[] = array(
            'field'    => $search_field,
            'operator' => $operator_name,
            'value'    => $value,
            'subtype'  => $subtype
        );
    }

    /**
     * parse_rules
     *
     * Takes an array of sanitized search data from the form and generates
     * our real array from it.
     * @param array $data
     */
    public function parse_rules($data)
    {
        $this->rules = array();
        foreach ($data as $rule => $search_field) {
            if ($search_field == 'name' && preg_match('/^rule_\d*$/', $rule)) {
                $search_field = 'title';
            }
            if (preg_match('/^rule_(\d+)$/', $rule, $ruleID)) {
                $ruleID                = (string) $ruleID[1];
                $input                 = (string) $data['rule_' . $ruleID . '_input'];
                $operator_index        = $data[ 'rule_' . $ruleID . '_operator'];
                $subtype               = $data[ 'rule_' . $ruleID . '_subtype'];
                $search_field_datatype = $this->get_searchfield_datatype($search_field);
                //add rule for each value in input
                foreach (explode('|', $input) as $value) {
                    $this->add_rule(
                        $search_field,
                        $this->operators[$search_field_datatype][$operator_index]['name'],
                        $value,
                        $subtype
                    );
                }
            }
        }
        $this->logic_operator = $data['operator'];
    }

    /**
     * save
     *
     * Save this search to the database for use as a smart playlist
     * @return string
     */
    public function save()
    {
        // Make sure we have a unique name
        if (! $this->name) {
            $time_format = AmpConfig::get('custom_datetime') ? (string) AmpConfig::get('custom_datetime') : 'm/d/Y H:i:s';
            $this->name  = Core::get_global('user')->username . ' - ' . get_datetime($time_format, time());
        }
        $sql        = "SELECT `id` FROM `search` WHERE `name` = ?";
        $db_results = Dba::read($sql, array($this->name));
        if (Dba::num_rows($db_results)) {
            $this->name .= uniqid('', true);
        }

        $sql = "INSERT INTO `search` (`name`, `type`, `user`, `rules`, `logic_operator`, `random`, `limit`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        Dba::write($sql, array($this->name, $this->type, Core::get_global('user')->id, json_encode($this->rules), $this->logic_operator, ($this->random > 0) ? 1 : 0, $this->limit));
        $insert_id = Dba::insert_id();
        $this->id  = (int) $insert_id;

        return $insert_id;
    }

    /**
     * to_js
     *
     * Outputs the javascript necessary to re-show the current set of rules.
     * @return string
     */
    public function to_js()
    {
        $js = "";
        foreach ($this->rules as $rule) {
            $js .= '<script>' .
                'SearchRow.add("' . $rule['field'] . '","' .
                $rule['operator'] . '","' . $rule['value'] . '", "' . $rule['subtype'] . '"); </script>';
        }

        return $js;
    }

    /**
     * to_sql
     *
     * Call the appropriate real function.
     * @return array
     */
    public function to_sql()
    {
        return call_user_func(array($this, $this->searchtype . "_to_sql"));
    }

    /**
     * update
     *
     * This function updates the saved version with the current settings.
     * @param array $data
     * @return integer
     */
    public function update(array $data = null)
    {
        if ($data && is_array($data)) {
            $this->name   = $data['name'];
            $this->type   = $data['pl_type'];
            $this->random = ((int) $data['random'] > 0) ? 1 : 0;
            $this->limit  = $data['limit'];
        }

        if (!$this->id) {
            return 0;
        }

        $sql = "UPDATE `search` SET `name` = ?, `type` = ?, `rules` = ?, `logic_operator` = ?, `random` = ?, `limit` = ? WHERE `id` = ?";
        Dba::write($sql, array($this->name, $this->type, json_encode($this->rules), $this->logic_operator, $this->random, $this->limit, $this->id));

        return $this->id;
    }

    /**
     * @return mixed|void
     */
    public static function garbage_collection()
    {
    }

    /**
     * _mangle_data
     *
     * Private convenience function.  Mangles the input according to a set
     * of predefined rules so that we don't have to include this logic in
     * foo_to_sql.
     * @param array $data
     * @param string|false $type
     * @param array $operator
     * @return array|bool|int|string|string[]|null
     */
    private function _mangle_data($data, $type, $operator)
    {
        if ($operator['preg_match']) {
            $data = preg_replace(
                $operator['preg_match'],
                $operator['preg_replace'],
                $data
            );
        }

        if ($type == 'numeric' || $type == 'days') {
            return (int) ($data);
        }

        if ($type == 'boolean') {
            return make_bool($data);
        }

        return $data;
    }


    /**
     * get_sql_vars_from_rule
     *
     * returns filtered input and the SQL comparison operator based on the given rule data
     * @param array $rule
     * @return array
     */
    private function get_sql_vars_from_rule($rule)
    {
        $type               = $this->get_searchfield_datatype($rule['field']);
        $operator           = $this->get_operator_array_by_name($type, $rule['operator']);
        $raw_input          = $this->_mangle_data($rule['value'], $type, $operator);
        $input              = filter_var($raw_input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $sql_match_operator = $operator['sql'];

        return array($input, $sql_match_operator);
    }

    /**
     * album_to_sql
     *
     * Handles the generation of the SQL for album searches.
     * @return array
     */
    private function album_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $userid             = $this->search_user->id;

        $where       = array();
        $table       = array();
        $join        = array();
        $group       = array();
        $having      = array();
        $join['tag'] = array();
        $groupdisks  = AmpConfig::get('album_group');

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);
            if ($groupdisks) {
                $group[] = "`album`.`prefix`";
                $group[] = "`album`.`name`";
                $group[] = "`album`.`album_artist`";
                $group[] = "`album`.`mbid`";
                $group[] = "`album`.`year`";
            } else {
                $group[] = "`album`.`id`";
            }

            switch ($rule['field']) {
                case 'title':
                    $where[] = "(`album`.`name` $sql_match_operator '$input' " .
                                " OR LTRIM(CONCAT(COALESCE(`album`.`prefix`, ''), " .
                                "' ', `album`.`name`)) $sql_match_operator '$input')";
                break;
                case 'year':
                    $where[] = "`album`.`year` $sql_match_operator '$input'";
                break;
                case 'time':
                    $input    = $input * 60;
                    $having[] = "SUM(`song`.`time`) $sql_match_operator '$input'";
                break;
                case 'rating':
                    if ($this->type != "public") {
                        $where[] = "COALESCE(`rating`.`rating`,0) $sql_match_operator '$input'";
                    } else {
                        $having[] = "ROUND(AVG(IFNULL(`rating`.`rating`,0))) $sql_match_operator '$input'";
                    }
                    $join['rating'] = true;
                break;
                case 'favorite':
                    $join['user_flag']  = true;
                    $where[]            = "(`album`.`name` $sql_match_operator '$input' " .
                                " OR LTRIM(CONCAT(COALESCE(`album`.`prefix`, ''), " .
                                "' ', `album`.`name`)) $sql_match_operator '$input') AND `user_flag`.`user` = $userid";
                    $where[] .= "`user_flag`.`object_type` = 'album'";
                break;
                case 'myrating':
                    $where[]          = "COALESCE(`rating`.`rating`,0) $sql_match_operator '$input'";
                    $join['myrating'] = true;
                break;
                case 'artistrating':
                    if ($sql_match_operator === '<=' || $sql_match_operator === '<>' || $sql_match_operator === '<=') {
                        $where[] = "(`song`.`artist` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'artist') OR " .
                                   "`song`.`artist` NOT IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE `rating`.`object_type` = 'artist'))";
                    } else {
                        $where[] = "`song`.`artist` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'artist') ";
                    }
                break;
                case 'myplayed':
                    $where[]                  = "`object_count`.`date` IS NOT NULL";
                    $join['object_count']     = true;
                    break;
                case 'last_play':
                    $where[]              = "`object_count`.`date` IN (SELECT MAX(`object_count`.`date`) FROM `object_count`  " .
                            "WHERE `object_count`.`object_type` = 'album' AND `object_count`.`count_type` = 'stream'  " .
                            "GROUP BY `object_count`.`object_id`) AND `object_count`.`date` $sql_match_operator (UNIX_TIMESTAMP() - ($input * 86400))";
                    $join['object_count'] = true;
                    break;
                case 'played_times':
                    $where[] = "`album`.`id` IN (SELECT `object_count`.`object_id` FROM `object_count` " .
                        "WHERE `object_count`.`object_type` = 'album' AND `object_count`.`count_type` = 'stream' " .
                        "GROUP BY `object_count`.`object_id` HAVING COUNT(*) $sql_match_operator '$input')";
                break;
                case 'other_user':
                    $other_userid = $input;
                    if ($sql_match_operator == 'userflag') {
                        $join['other_user_flag'] = true;
                        $where[]                 = "`user_flag`.`user` = $other_userid " .
                                   " AND `user_flag`.`object_type` = 'album'";
                    } else {
                        $join['other_rating'] = true;
                        $where[]              = $sql_match_operator .
                                   " AND `rating`.`user` = $other_userid " .
                                   " AND `rating`.`object_type` = 'album'";
                    }
                break;
                case 'catalog':
                    $where[]      = "`song`.`catalog` $sql_match_operator '$input'";
                    $join['song'] = true;
                break;
                case 'tag':
                    $key = md5($input . $sql_match_operator);
                    if ($sql_match_operator == 'LIKE' || $sql_match_operator == 'NOT LIKE') {
                        $where[]           = "`realtag_$key`.`name` $sql_match_operator '$input'";
                        $join['tag'][$key] = "$sql_match_operator '$input'";
                    } else {
                        $where[]           = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                        $join['tag'][$key] = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                    }
                break;
                case 'image height':
                    $where[]       = "`image`.`height` $sql_match_operator '$input'";
                    $join['image'] = true;
                break;
                case 'image width':
                    $where[]       = "`image`.`width` $sql_match_operator '$input'";
                    $join['image'] = true;
                break;
                case 'artist':
                    $where[]        = "(`artist`.`name` $sql_match_operator '$input' OR LTRIM(CONCAT(COALESCE(`artist`.`prefix`, ''), ' ', `artist`.`name`)) $sql_match_operator '$input')";
                    $join['artist'] = true;
                break;
                default:
                    // Nae laird!
                break;
            } // switch on ruletype
        } // foreach rule

        $join['song']    = $join['song'] || AmpConfig::get('catalog_disable');
        $join['catalog'] = $join['song'] || AmpConfig::get('catalog_disable');

        $where_sql = implode(" $sql_logic_operator ", $where);

        foreach ($join['tag'] as $key => $value) {
            $table['tag_' . $key] =
                "LEFT JOIN (" .
                "SELECT `object_id`, GROUP_CONCAT(`name`) AS `name` " .
                "FROM `tag` LEFT JOIN `tag_map` " .
                "ON `tag`.`id`=`tag_map`.`tag_id` " .
                "WHERE `tag_map`.`object_type`='album' " .
                "GROUP BY `object_id`" .
                ") AS `realtag_$key` " .
                "ON `album`.`id`=`realtag_$key`.`object_id`";
        }
        if ($join['artist']) {
            $table['artist'] = "LEFT JOIN `artist` ON `artist`.`id`=`album`.`album_artist`";
        }
        if ($join['song']) {
            $table['song'] = "LEFT JOIN `song` ON `song`.`album`=`album`.`id`";

            if ($join['catalog']) {
                $table['catalog'] = "LEFT JOIN `catalog` AS `catalog_se` ON `catalog_se`.`id`=`song`.`catalog`";
                if (!empty($where_sql)) {
                    $where_sql .= " AND `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                } else {
                    $where_sql .= " `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                }
            }
        }
        if ($join['user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `user_flag`.`object_type`='album' AND " .
                                   "`album`.`id`=`user_flag`.`object_id`";
        }
        if ($join['other_user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `user_flag`.`object_type`='album' AND " .
                                   "`album`.`id`=`user_flag`.`object_id` ";
        }
        if ($join['rating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='album' AND ";
            if ($this->type != "public") {
                $table['rating'] .= "`rating`.`user`='" . $userid . "' AND ";
            }
            $table['rating'] .= "`rating`.`object_id`=`album`.`id`";
        }
        if ($join['other_rating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='album' AND ";
            $table['rating'] .= "`rating`.`object_id`=`album`.`id`";
        }
        if ($join['myrating']) {
            $table['myrating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='album' AND ";
            $table['myrating'] .= "`rating`.`user`='" . $userid . "' AND ";
            $table['myrating'] .= "`rating`.`object_id`=`album`.`id`";
        }
        if ($join['object_count']) {
            $table['object_count'] = "LEFT JOIN (SELECT `object_count`.`object_id`, MAX(`object_count`.`date`) AS " .
                    "`date` FROM `object_count` WHERE `object_count`.`object_type` = 'album' AND " .
                    "`object_count`.`user`='" . $userid . "' AND `object_count`.`count_type` = 'stream' GROUP BY `object_count`.`object_id`) AS " .
                    "`object_count` ON `object_count`.`object_id`=`album`.`id`";
        }
        if ($join['image']) {
            $table['song'] = "LEFT JOIN `song` ON `song`.`album`=`album`.`id` LEFT JOIN `image` ON `image`.`object_id`=`album`.`id`";
            $where_sql .= " AND `image`.`object_type`='album'";
            $where_sql .= " AND `image`.`size`='original'";
        }

        $table_sql  = implode(' ', $table);
        $group_sql  = implode(',', $group);
        $having_sql = implode(" $sql_logic_operator ", $having);

        return array(
            'base' => 'SELECT DISTINCT(`album`.`id`) FROM `album`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => $table_sql,
            'group_sql' => $group_sql,
            'having_sql' => $having_sql
        );
    }

    /**
     * artist_to_sql
     *
     * Handles the generation of the SQL for artist searches.
     * @return array
     */
    private function artist_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $userid             = $this->search_user->id;

        $where              = array();
        $table              = array();
        $join               = array();
        $group              = array();
        $having             = array();
        $join['tag']        = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);

            switch ($rule['field']) {
                case 'title':
                case 'name':
                    $where[] = "(`artist`.`name` $sql_match_operator '$input' " .
                                " OR LTRIM(CONCAT(COALESCE(`artist`.`prefix`, ''), " .
                                "' ', `artist`.`name`)) $sql_match_operator '$input')";
                break;
                case 'yearformed':
                    $where[] = "`artist`.`yearformed` $sql_match_operator '$input'";
                break;
                case 'placeformed':
                    $where[] = "`artist`.`placeformed` $sql_match_operator '$input'";
                break;
                case 'tag':
                    $key = md5($input . $sql_match_operator);
                    if ($sql_match_operator == 'LIKE' || $sql_match_operator == 'NOT LIKE') {
                        $where[]           = "`realtag_$key`.`name` $sql_match_operator '$input'";
                        $join['tag'][$key] = "$sql_match_operator '$input'";
                    } else {
                        $where[]           = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                        $join['tag'][$key] = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                    }
                break;
                case 'rating':
                    if ($this->type != "public") {
                        $where[] = "COALESCE(`rating`.`rating`,0) $sql_match_operator '$input'";
                    } else {
                        $group[]  = "`artist`.`id`";
                        $having[] = "ROUND(AVG(IFNULL(`rating`.`rating`,0))) $sql_match_operator '$input'";
                    }
                    $join['rating'] = true;
                break;
                case 'favorite':
                    $join['user_flag']  = true;
                    $where[]            = "(`artist`.`name` $sql_match_operator '$input' " .
                                " OR LTRIM(CONCAT(COALESCE(`artist`.`prefix`, ''), " .
                                "' ', `artist`.`name`)) $sql_match_operator '$input') AND `user_flag`.`user` = $userid";
                    $where[] .= "`user_flag`.`object_type` = 'artist'";
                break;
                case 'image height':
                    $where[]       = "`image`.`height` $sql_match_operator '$input'";
                    $join['image'] = true;
                break;
                case 'image width':
                    $where[]       = "`image`.`width` $sql_match_operator '$input'";
                    $join['image'] = true;
                break;
                case 'myrating':
                    $where[]           = "COALESCE(`rating`.`rating`,0) $sql_match_operator '$input'";
                    $join['myrating']  = true;
                break;
                case 'myplayed':
                    $where[]              = "`object_count`.`date` IS NOT NULL";
                    $join['object_count'] = true;
                    break;
                case 'last_play':
                    $where[]              = "`object_count`.`date` IN (SELECT MAX(`object_count`.`date`) FROM `object_count` " .
                        "WHERE `object_count`.`object_type` = 'artist' AND `object_count`.`count_type` = 'stream' " .
                        "GROUP BY `object_count`.`object_id`) AND `object_count`.`date` $sql_match_operator (UNIX_TIMESTAMP() - ($input * 86400))";
                    $join['object_count'] = true;
                    break;
                case 'played_times':
                    $where[] = "`artist`.`id` IN (SELECT `object_count`.`object_id` FROM `object_count` " .
                        "WHERE `object_count`.`object_type` = 'artist' AND `object_count`.`count_type` = 'stream' " .
                        "GROUP BY `object_count`.`object_id` HAVING COUNT(*) $sql_match_operator '$input')";
                break;
                case 'other_user':
                    $other_userid = $input;
                    if ($sql_match_operator == 'userflag') {
                        $join['other_user_flag'] = true;
                        $where[]                 = "`user_flag`.`user` = $other_userid " .
                                   " AND `user_flag`.`object_type` = 'artist'";
                    } else {
                        $join['other_rating'] = true;
                        $where[]              = $sql_match_operator .
                                   " AND `rating`.`user` = $other_userid " .
                                   " AND `rating`.`object_type` = 'artist'";
                    }
                break;
                default:
                    // Nihil
                break;
            } // switch on ruletype
        } // foreach rule

        $join['song']    = $join['song'] || AmpConfig::get('catalog_disable');
        $join['catalog'] = AmpConfig::get('catalog_disable');

        $where_sql = implode(" $sql_logic_operator ", $where);

        foreach ($join['tag'] as $key => $value) {
            $table['tag_' . $key] =
                "LEFT JOIN (" .
                "SELECT `object_id`, GROUP_CONCAT(`name`) AS `name` " .
                "FROM `tag` LEFT JOIN `tag_map` " .
                "ON `tag`.`id`=`tag_map`.`tag_id` " .
                "WHERE `tag_map`.`object_type`='artist' " .
                "GROUP BY `object_id`" .
                ") AS `realtag_$key` " .
                "ON `artist`.`id`=`realtag_$key`.`object_id`";
        }

        if ($join['song']) {
            $table['song'] = "LEFT JOIN `song` ON `song`.`artist`=`artist`.`id`";

            if ($join['catalog']) {
                $table['catalog'] = "LEFT JOIN `catalog` AS `catalog_se` ON `catalog_se`.`id`=`song`.`catalog`";
                if (!empty($where_sql)) {
                    $where_sql .= " AND `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                } else {
                    $where_sql .= " `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                }
            }
        }
        if ($join['user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `user_flag`.`object_type`='artist' AND " .
                                   "`artist`.`id`=`user_flag`.`object_id`";
        }
        if ($join['other_user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `user_flag`.`object_type`='artist' AND " .
                                   "`artist`.`id`=`user_flag`.`object_id` ";
        }
        if ($join['rating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='artist' AND ";
            if ($this->type != "public") {
                $table['rating'] .= "`rating`.`user`='" . $userid . "' AND ";
            }
            $table['rating'] .= "`rating`.`object_id`=`artist`.`id`";
        }
        if ($join['other_rating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='artist' AND ";
            $table['rating'] .= "`rating`.`object_id`=`artist`.`id`";
        }
        if ($join['myrating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='artist' AND ";
            $table['rating'] .= "`rating`.`user`='" . $userid . "' AND ";
            $table['rating'] .= "`rating`.`object_id`=`artist`.`id`";
        }
        if ($join['object_count']) {
            $table['object_count'] = "LEFT JOIN (SELECT `object_count`.`object_id`, MAX(`object_count`.`date`) AS " .
                    "`date` FROM `object_count` WHERE `object_count`.`object_type` = 'artist' AND " .
                    "`object_count`.`user`='" . $userid . "' AND `object_count`.`count_type` = 'stream' GROUP BY `object_count`.`object_id`) AS " .
                    "`object_count` ON `object_count`.`object_id`=`artist`.`id`";
        }
        if ($join['image']) {
            $table['song'] = "LEFT JOIN `song` ON `song`.`artist`=`artist`.`id` LEFT JOIN `image` ON `image`.`object_id`=`artist`.`id`";
            $where_sql .= " AND `image`.`object_type`='artist'";
            $where_sql .= " AND `image`.`size`='original'";
        }
        $table_sql  = implode(' ', $table);
        $group_sql  = implode(',', $group);
        $having_sql = implode(" $sql_logic_operator ", $having);

        return array(
            'base' => 'SELECT DISTINCT(`artist`.`id`) FROM `artist`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => $table_sql,
            'group_sql' => $group_sql,
            'having_sql' => $having_sql
        );
    }

    /**
     * song_to_sql
     * Handles the generation of the SQL for song searches.
     * @return array
     */
    private function song_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $userid             = $this->search_user->id;

        $where       = array();
        $table       = array();
        $join        = array();
        $group       = array();
        $having      = array();
        $join['tag'] = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);
            $addition_string    = '';
            $update_string      = '';

            switch ($rule['field']) {
                case 'anywhere':
                    $where[]           = "((`artist`.`name` $sql_match_operator '$input' OR LTRIM(CONCAT(COALESCE(`artist`.`prefix`, ''), ' ', `artist`.`name`)) $sql_match_operator '$input') OR " .
                                         "(`album`.`name` $sql_match_operator '$input' OR LTRIM(CONCAT(COALESCE(`album`.`prefix`, ''), ' ', `album`.`name`)) $sql_match_operator '$input') OR " .
                                         " `song_data`.`comment` $sql_match_operator '$input' OR `song_data`.`label` $sql_match_operator '$input' OR `song`.`file` $sql_match_operator '$input' OR `song`.`title` $sql_match_operator '$input')";
                    $join['album']     = true;
                    $join['artist']    = true;
                    $join['song_data'] = true;
                break;
                case 'tag':
                    $key = md5($input . $sql_match_operator);
                    if ($sql_match_operator == 'LIKE' || $sql_match_operator == 'NOT LIKE') {
                        $where[]           = "`realtag_$key`.`name` $sql_match_operator '$input'";
                        $join['tag'][$key] = "$sql_match_operator '$input'";
                    } else {
                        $where[]           = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                        $join['tag'][$key] = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                    }
                break;
                case 'album_tag':
                    $key           = md5($input . $sql_match_operator);
                    $join['album'] = true;
                    if ($sql_match_operator == 'LIKE' || $sql_match_operator == 'NOT LIKE') {
                        $where[]                 = "`realtag_$key`.`name` $sql_match_operator '$input'";
                        $join['album_tag'][$key] = "$sql_match_operator '$input'";
                    } else {
                        $where[]           = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                        $join['tag'][$key] = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                    }
                break;
                case 'artist_tag':
                    $key            = md5($input . $sql_match_operator);
                    $join['artist'] = true;
                    if ($sql_match_operator == 'LIKE' || $sql_match_operator == 'NOT LIKE') {
                        $where[]                  = "`realtag_$key`.`name` $sql_match_operator '$input'";
                        $join['artist_tag'][$key] = "$sql_match_operator '$input'";
                    } else {
                        $where[]           = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                        $join['tag'][$key] = "find_in_set('$input', cast(`realtag_$key`.`name` as char)) $sql_match_operator 0";
                    }
                break;
                case 'title':
                    $where[] = "`song`.`title` $sql_match_operator '$input'";
                break;
                case 'album':
                    $where[] = "(`album`.`name` $sql_match_operator '$input' " .
                               " OR LTRIM(CONCAT(COALESCE(`album`.`prefix`, ''), " .
                               "' ', `album`.`name`)) $sql_match_operator '$input')";
                    $join['album'] = true;
                break;
                case 'artist':
                    $group[]        = "`artist`.`id`";
                    $where[]        = "(`artist`.`name` $sql_match_operator '$input' " .
                                      " OR LTRIM(CONCAT(COALESCE(`artist`.`prefix`, ''), " .
                                      "' ', `artist`.`name`)) $sql_match_operator '$input')";
                    $join['artist'] = true;
                break;
                case 'composer':
                    $where[] = "`song`.`composer` $sql_match_operator '$input'";
                break;
                case 'time':
                    $input   = $input * 60;
                    $where[] = "`song`.`time` $sql_match_operator '$input'";
                break;
                case 'file':
                    $where[] = "`song`.`file` $sql_match_operator '$input'";
                break;
                case 'year':
                    $where[] = "`song`.`year` $sql_match_operator '$input'";
                break;
                case 'comment':
                    $where[]           = "`song_data`.`comment` $sql_match_operator '$input'";
                    $join['song_data'] = true;
                break;
                case 'label':
                    $where[]           = "`song_data`.`label` $sql_match_operator '$input'";
                    $join['song_data'] = true;
                break;
                case 'played':
                    $where[] = " `song`.`played` = '$sql_match_operator'";
                break;
                case 'myplayed':
                    $group[]              = "`song`.`id`";
                    $having[]             = "COUNT(`object_count`.`object_id`) = " . $sql_match_operator;
                    $join['object_count'] = true;
                break;
                case 'last_play':
                    $where[]              = "`object_count`.`date` $sql_match_operator (UNIX_TIMESTAMP() - ($input * 86400))";
                    $join['object_count'] = true;
                    break;
                case 'played_times':
                    $where[] = "`song`.`id` IN (SELECT `object_count`.`object_id` FROM `object_count` " .
                        "WHERE `object_count`.`object_type` = 'song' AND `object_count`.`count_type` = 'stream' " .
                        "GROUP BY `object_count`.`object_id` HAVING COUNT(*) $sql_match_operator '$input')";
                break;
                case 'myplayedalbum':
                    $group[]                     = "`song`.`id`";
                    $having[]                    = "COUNT(`object_count_album`.`object_id`) = " . $sql_match_operator;
                    $join['album']               = true;
                    $join['object_count_album']  = true;
                    break;
                case 'myplayedartist':
                    $group[]                      = "`song`.`id`";
                    $having[]                     = "COUNT(`object_count_artist`.`object_id`) = " . $sql_match_operator;
                    $join['artist']               = true;
                    $join['object_count_artist']  = true;
                    break;
                case 'bitrate':
                    $input   = $input * 1000;
                    $where[] = "`song`.`bitrate` $sql_match_operator '$input'";
                break;
                case 'rating':
                    if ($this->type != "public") {
                        $where[] = "COALESCE(`rating`.`rating`,0) $sql_match_operator '$input'";
                    } else {
                        $group[]  = "`song`.`id`";
                        $having[] = "ROUND(AVG(IFNULL(`rating`.`rating`,0))) $sql_match_operator '$input'";
                    }
                    $join['rating'] = true;
                break;
                case 'favorite':
                    $join['user_flag']  = true;
                    $where[]            = "`song`.`title` $sql_match_operator '$input' AND `user_flag`.`user` = $userid " .
                                          " AND `user_flag`.`object_type` = 'song'";
                break;
                case 'myrating':
                    $group[]           = "`song`.`id`";
                    $having[]          = "ROUND(AVG(IFNULL(`rating`.`rating`,0))) $sql_match_operator '$input'";
                    $join['myrating']  = true;
                break;
                case 'albumrating':
                    if ($sql_match_operator === '<=' || $sql_match_operator === '<>' || $sql_match_operator === '<=') {
                        $where[] = "(`song`.`album` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'album') OR " .
                                   "`song`.`album` NOT IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE `rating`.`object_type` = 'album'))";
                    } else {
                        $where[] = "`song`.`album` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'album') ";
                    }
                break;
                case 'artistrating':
                    if ($sql_match_operator === '<=' || $sql_match_operator === '<>' || $sql_match_operator === '<=') {
                        $where[] = "(`song`.`artist` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'artist') OR " .
                                   "`song`.`artist` NOT IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE `rating`.`object_type` = 'artist'))";
                    } else {
                        $where[] = "`song`.`artist` IN (SELECT `rating`.`object_id` FROM `rating` " .
                                   "WHERE COALESCE(`rating`.`rating`,0) $sql_match_operator '$input' AND " .
                                   "`rating`.`user`='" . $userid . "' AND `rating`.`object_type` = 'artist') ";
                    }
                break;
                case 'catalog':
                    $where[] = "`song`.`catalog` $sql_match_operator '$input'";
                break;
                case 'other_user':
                    $other_userid = $input;
                    if ($sql_match_operator == 'userflag') {
                        $join['other_user_flag'] = true;
                        $where[]                 = "`user_flag`.`user` = $other_userid " .
                                   " AND `user_flag`.`object_type` = 'song'";
                    } else {
                        $join['other_rating'] = true;
                        $where[]              = $sql_match_operator .
                                   " AND `rating`.`user` = $other_userid " .
                                   " AND `rating`.`object_type` = 'song'";
                    }
                break;
                case 'other_user_album':
                    $other_userid = $input;
                    if ($sql_match_operator == 'userflag') {
                        $join['other_user_flag_album'] = true;
                        $where[]                       = "`user_flag`.`user` = $other_userid " .
                                   " AND `user_flag`.`object_type` = 'album'";
                    } else {
                        $join['other_rating_album'] = true;
                        $where[]                    = $sql_match_operator .
                                   " AND `rating`.`user` = $other_userid " .
                                   " AND `rating`.`object_type` = 'album'";
                    }
                break;
                case 'other_user_artist':
                    $other_userid = $input;
                    if ($sql_match_operator == 'userflag') {
                        $join['other_user_flag_artist'] = true;
                        $where[]                        = "`user_flag`.`user` = $other_userid " .
                                   " AND `user_flag`.`object_type` = 'artist'";
                    } else {
                        $join['other_rating_artist'] = true;
                        $where[]                     = $sql_match_operator .
                                   " AND `rating`.`user` = $other_userid " .
                                   " AND `rating`.`object_type` = 'artist'";
                    }
                break;
                case 'playlist_name':
                    $join['playlist']      = true;
                    $join['playlist_data'] = true;
                    $where[]               = "`playlist`.`name` $sql_match_operator '$input'";
                break;
                case 'playlist':
                    $join['playlist_data'] = true;
                    $where[]               = "`playlist_data`.`playlist` $sql_match_operator '$input'";
                break;
                case 'smartplaylist':
                    $subsearch    = new Search($input, 'song', $this->search_user);
                    $subsql       = $subsearch->to_sql();
                    $results      = $subsearch->get_items();
                    $itemstring   = '';
                    if (count($results) > 0) {
                        foreach ($results as $item) {
                            $itemstring .= ' ' . $item['object_id'] . ',';
                        }

                        $where[]      = "$sql_match_operator `song`.`id` IN (" . substr($itemstring, 0, -1) . ")";
                        // HACK: array_merge would potentially lose tags, since it
                        // overwrites. Save our merged tag joins in a temp variable,
                        // even though that's ugly.
                        $tagjoin     = array_merge($subsql['join']['tag'], $join['tag']);
                        $join        = array_merge($subsql['join'], $join);
                        $join['tag'] = $tagjoin;
                    }
                break;
                case 'license':
                    $where[] = "`song`.`license` $sql_match_operator '$input'";
                break;
                case 'added':
                    $input   = strtotime($input);
                    $where[] = "`song`.`addition_time` $sql_match_operator $input";
                break;
                case 'updated':
                    $update_string = '';
                    $input         = strtotime($input);
                    $where[]       = "`song`.`update_time` $sql_match_operator $input";
                    break;
                case 'recent_added':
                    $where[]          = "`addition_time`.`id` IS NOT NULL";
                    $addition_string  = "LEFT JOIN (SELECT `id` from `song` ORDER BY $sql_match_operator DESC LIMIT $input) as `addition_time` ON `song`.`id` = `addition_time`.`id`";
                    $join['addition'] = true;
                    break;
                case 'recent_updated':
                    $where[]        = "`update_time`.`id` IS NOT NULL";
                    $update_string  = "LEFT JOIN (SELECT `id` from `song` ORDER BY $sql_match_operator DESC LIMIT $input) as `update_time` ON `song`.`id` = `update_time`.`id`";
                    $join['update'] = true;
                    break;
                case 'metadata':
                    // Need to create a join for every field so we can create and / or queries with only one table
                    $tableAlias         = 'metadata' . uniqid();
                    $field              = (int) $rule['subtype'];
                    $join[$tableAlias]  = true;
                    $parsedInput        = is_numeric($input) ? $input : '"' . $input . '"';
                    $where[]            = "(`$tableAlias`.`field` = {$field} AND `$tableAlias`.`data` $sql_match_operator $parsedInput)";
                    $table[$tableAlias] = 'LEFT JOIN `metadata` AS ' . $tableAlias . ' ON `song`.`id` = `' . $tableAlias . '`.`object_id`';
                    break;
                default:
                    // NOSSINK!
                break;
            } // switch on type
        } // foreach over rules

        $join['catalog'] = AmpConfig::get('catalog_disable');

        $where_sql = implode(" $sql_logic_operator ", $where);

        // now that we know which things we want to JOIN...
        if ($join['artist']) {
            $table['artist'] = "LEFT JOIN `artist` ON `song`.`artist`=`artist`.`id`";
        }
        if ($join['album']) {
            $table['album'] = "LEFT JOIN `album` ON `song`.`album`=`album`.`id`";
        }
        if ($join['song_data']) {
            $table['song_data'] = "LEFT JOIN `song_data` ON `song`.`id`=`song_data`.`song_id`";
        }
        if ($join['tag']) {
            foreach ($join['tag'] as $key => $value) {
                $table['tag_' . $key] =
                    "LEFT JOIN (" .
                    "SELECT `object_id`, GROUP_CONCAT(`name`) AS `name` " .
                    "FROM `tag` LEFT JOIN `tag_map` " .
                    "ON `tag`.`id`=`tag_map`.`tag_id` " .
                    "WHERE `tag_map`.`object_type`='song' " .
                    "GROUP BY `object_id`" .
                    ") AS `realtag_$key` " .
                    "ON `song`.`id`=`realtag_$key`.`object_id`";
            }
        }
        if ($join['album_tag']) {
            foreach ($join['album_tag'] as $key => $value) {
                $table['tag_' . $key] =
                    "LEFT JOIN (" .
                    "SELECT `object_id`, GROUP_CONCAT(`name`) AS `name` " .
                    "FROM `tag` LEFT JOIN `tag_map` " .
                    "ON `tag`.`id`=`tag_map`.`tag_id` " .
                    "WHERE `tag_map`.`object_type`='album' " .
                    "GROUP BY `object_id`" .
                    ") AS realtag_$key " .
                    "ON `album`.`id`=`realtag_$key`.`object_id`";
            }
        }
        if ($join['artist_tag']) {
            foreach ($join['artist_tag'] as $key => $value) {
                $table['tag_' . $key] =
                    "LEFT JOIN (" .
                    "SELECT `object_id`, GROUP_CONCAT(`name`) AS `name` " .
                    "FROM `tag` LEFT JOIN `tag_map` " .
                    "ON `tag`.`id`=`tag_map`.`tag_id` " .
                    "WHERE `tag_map`.`object_type`='artist' " .
                    "GROUP BY `object_id`" .
                    ") AS realtag_$key " .
                    "ON `artist`.`id`=`realtag_$key`.`object_id`";
            }
        }
        if ($join['rating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='song' AND ";
            if ($this->type != "public") {
                $table['rating'] .= "`rating`.`user`='" . $userid . "' AND ";
            }
            $table['rating'] .= "`rating`.`object_id`=`song`.`id`";
        }

        if ($join['user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `song`.`id`=`user_flag`.`object_id` ";
        }
        if ($join['other_user_flag']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `song`.`id`=`user_flag`.`object_id` ";
        }
        if ($join['other_user_flag_album']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `song`.`album`=`user_flag`.`object_id` ";
        }
        if ($join['other_user_flag_artist']) {
            $table['user_flag']  = "LEFT JOIN `user_flag` ON `song`.`artist`=`user_flag`.`object_id` ";
        }
        if ($join['myrating']) {
            $table['rating'] = "LEFT JOIN `rating` ON `rating`.`object_type`='song' AND ";
            $table['rating'] .= "`rating`.`user`='" . $userid . "' AND ";
            $table['rating'] .= "`rating`.`object_id`=`song`.`id`";
        }
        if ($join['other_rating']) {
            $table['rating'] = "LEFT JOIN `rating` AS `rating` ON `rating`.`object_type`='song' AND ";
            $table['rating'] .= "`rating`.`object_id`=`song`.`id`";
        }
        if ($join['other_rating_album']) {
            $table['rating'] = "LEFT JOIN `rating` AS `rating` ON `rating`.`object_type`='album' AND ";
            $table['rating'] .= "`rating`.`object_id`=`song`.`album`";
        }
        if ($join['other_rating_artist']) {
            $table['rating'] = "LEFT JOIN `rating` AS `rating` ON `rating`.`object_type`='artist' AND ";
            $table['rating'] .= "`rating`.`object_id`=`song`.`artist`";
        }
        if ($join['object_count']) {
            $table['object_count'] = "LEFT JOIN (SELECT `object_count`.`object_id`, MAX(`object_count`.`date`) AS " .
                    "`date` FROM `object_count` WHERE `object_count`.`object_type` = 'song' AND " .
                    "`object_count`.`user`='" . $userid . "' AND `object_count`.`count_type` = 'stream' GROUP BY `object_count`.`object_id`) AS " .
                    "`object_count` ON `object_count`.`object_id`=`song`.`id`";
        }
        if ($join['object_count_album']) {
            $table['object_count_album'] = "LEFT JOIN (SELECT `object_count`.`object_id`, MAX(`object_count`.`date`) AS " .
                    "`date` FROM `object_count` WHERE `object_count`.`object_type` = 'album' AND " .
                    "`object_count`.`user`='" . $userid . "' AND `object_count`.`count_type` = 'stream' GROUP BY `object_count`.`object_id`) AS " .
                    "`object_count_album` ON `object_count_album`.`object_id`=`album`.`id`";
        }
        if ($join['object_count_artist']) {
            $table['object_count_artist'] = "LEFT JOIN (SELECT `object_count`.`object_id`, MAX(`object_count`.`date`) AS " .
                    "`date` FROM `object_count` WHERE `object_count`.`object_type` = 'artist' AND " .
                    "`object_count`.`user`='" . $userid . "' AND `object_count`.`count_type` = 'stream' GROUP BY `object_count`.`object_id`) AS " .
                    "`object_count_artist` ON `object_count_artist`.`object_id`=`artist`.`id`";
        }
        if ($join['playlist_data']) {
            $table['playlist_data'] = "LEFT JOIN `playlist_data` ON `song`.`id`=`playlist_data`.`object_id` AND `playlist_data`.`object_type`='song'";
            if ($join['playlist']) {
                $table['playlist'] = "LEFT JOIN `playlist` ON `playlist_data`.`playlist`=`playlist`.`id`";
            }
        }
        if ($join['addition']) {
            $table['addition'] = $addition_string;
        }
        if ($join['update']) {
            $table['update'] = $update_string;
        }
        if ($join['catalog']) {
            $table['catalog'] = "LEFT JOIN `catalog` AS `catalog_se` ON `catalog_se`.`id`=`song`.`catalog`";
            if (!empty($where_sql)) {
                $where_sql .= " AND `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
            } else {
                $where_sql .= " `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
            }
        }

        $table_sql  = implode(' ', $table);
        $group_sql  = implode(',', $group);
        $having_sql = implode(" $sql_logic_operator ", $having);

        return array(
            'base' => 'SELECT DISTINCT(`song`.`id`) FROM `song`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => $table_sql,
            'group_sql' => $group_sql,
            'having_sql' => $having_sql
        );
    }

    /**
     * video_to_sql
     *
     * Handles the generation of the SQL for video searches.
     * @return array
     */
    private function video_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;

        $where  = array();
        $table  = array();
        $join   = array();
        $group  = array();
        $having = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);

            switch ($rule['field']) {
                case 'filename':
                    $where[] = "`video`.`file` $sql_match_operator '$input'";
                break;
                default:
                    // WE WILLNA BE FOOLED AGAIN!
            } // switch on ruletype
        } // foreach rule

        $join['catalog'] = AmpConfig::get('catalog_disable');

        $where_sql = implode(" $sql_logic_operator ", $where);

        if ($join['catalog']) {
            $table['catalog'] = "LEFT JOIN `catalog` AS `catalog_se` ON `catalog_se`.`id`=`video`.`catalog`";
            if (!empty($where_sql)) {
                $where_sql .= " AND `catalog_se`.`enabled` = '1'";
            } else {
                $where_sql .= " `catalog_se`.`enabled` = '1'";
            }
        }

        $table_sql  = implode(' ', $table);
        $group_sql  = implode(',', $group);
        $having_sql = implode(" $sql_logic_operator ", $having);

        return array(
            'base' => 'SELECT DISTINCT(`video`.`id`) FROM `video`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => $table_sql,
            'group_sql' => $group_sql,
            'having_sql' => $having_sql
        );
    }

    /**
     * playlist_to_sql
     *
     * Handles the generation of the SQL for playlist searches.
     * @return array
     */
    private function playlist_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $where              = array();
        $table              = array();
        $join               = array();
        $group              = array();
        $having             = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);

            $where[] = "`playlist`.`type` = 'public'";

            switch ($rule['field']) {
                case 'title':
                case 'name':
                    $where[] = "`playlist`.`name` $sql_match_operator '$input'";
                break;
                default:
                    // Nihil
                break;
            } // switch on ruletype
        } // foreach rule

        $join['playlist_data'] = true;
        $join['song']          = $join['song'] || AmpConfig::get('catalog_disable');
        $join['catalog']       = AmpConfig::get('catalog_disable');

        $where_sql = implode(" $sql_logic_operator ", $where);

        if ($join['playlist_data']) {
            $table['playlist_data'] = "LEFT JOIN `playlist_data` ON `playlist_data`.`playlist` = `playlist`.`id`";
        }

        if ($join['song']) {
            $table['song'] = "LEFT JOIN `song` ON `song`.`id`=`playlist_data`.`object_id`";
            $where_sql .= " AND `playlist_data`.`object_type` = 'song'";

            if ($join['catalog']) {
                $table['catalog'] = "LEFT JOIN `catalog` AS `catalog_se` ON `catalog_se`.`id`=`song`.`catalog`";
                if (!empty($where_sql)) {
                    $where_sql .= " AND `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                } else {
                    $where_sql .= " `catalog_se`.`enabled` = '1' AND `song`.`enabled` = 1";
                }
            }
        }

        $table_sql  = implode(' ', $table);
        $group_sql  = implode(',', $group);
        $having_sql = implode(" $sql_logic_operator ", $having);

        return array(
            'base' => 'SELECT DISTINCT(`playlist`.`id`) FROM `playlist`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => $table_sql,
            'group_sql' => $group_sql,
            'having_sql' => $having_sql
        );
    }

    /**
     * label_to_sql
     *
     * Handles the generation of the SQL for label searches.
     * @return array
     */
    private function label_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $where              = array();
        $table              = array();
        $join               = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);

            switch ($rule['field']) {
                case 'title':
                case 'name':
                    $where[] = "`label`.`name` $sql_match_operator '$input'";
                break;
                case 'category':
                    $where[] = "`label`.`category` $sql_match_operator '$input'";
                break;
                default:
                    // Nihil
                break;
            } // switch on ruletype
        } // foreach rule

        $where_sql = implode(" $sql_logic_operator ", $where);

        return array(
            'base' => 'SELECT DISTINCT(`label`.`id`) FROM `label`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => '',
            'group_sql' => '',
            'having_sql' => ''
        );
    }

    /**
     * user_to_sql
     *
     * Handles the generation of the SQL for user searches.
     * @return array
     */
    private function user_to_sql()
    {
        $sql_logic_operator = $this->logic_operator;
        $where              = array();
        $table              = array();
        $join               = array();

        foreach ($this->rules as $rule) {
            list($input, $sql_match_operator) = $this->get_sql_vars_from_rule($rule);

            switch ($rule['field']) {
                case 'username':
                    $where[] = "`user`.`username` $sql_match_operator '$input'";
                break;
                default:
                    // Nihil
                break;
            } // switch on ruletype
        } // foreach rule

        $where_sql = implode(" $sql_logic_operator ", $where);

        return array(
            'base' => 'SELECT DISTINCT(`user`.`id`) FROM `user`',
            'join' => $join,
            'where' => $where,
            'where_sql' => $where_sql,
            'table' => $table,
            'table_sql' => '',
            'group_sql' => '',
            'having_sql' => ''
        );
    }

    /**
     * year_search
     *
     * Build search rules for year -> year searching.
     * @param $fromYear
     * @param $toYear
     * @param $size
     * @param $offset
     * @return array
     */
    public static function year_search($fromYear, $toYear, $size, $offset)
    {
        $search           = array();
        $search['limit']  = $size;
        $search['offset'] = $offset;
        $search['type']   = "album";
        $count            = 0;
        if ($fromYear) {
            $search['rule_' . $count . '_input']    = $fromYear;
            $search['rule_' . $count . '_operator'] = 0;
            $search['rule_' . $count . '']          = "year";
            ++$count;
        }
        if ($toYear) {
            $search['rule_' . $count . '_input']    = $toYear;
            $search['rule_' . $count . '_operator'] = 1;
            $search['rule_' . $count . '']          = "year";
            ++$count;
        }

        return $search;
    }
} // end search.class
