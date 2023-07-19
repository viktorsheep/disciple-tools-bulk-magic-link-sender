<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.


/**
 * Class Disciple_Tools_Magic_Links_Magic_User_App
 */
class Disciple_Tools_Magic_Links_Magic_User_App extends DT_Magic_Url_Base
{

    public $page_title = 'User Contact Updates';
    public $page_description = 'An update summary of assigned contacts.';
    public $root = "smart_links"; // @todo define the root of the url {yoursite}/root/type/key/action
    public $type = 'user_contacts_updates'; // @todo define the type
    public $post_type = 'user';
    private $meta_key = '';

    private static $_instance = null;
    public $meta = []; // Allows for instance specific data.
    public $translatable = [
        'query',
        'user',
        'contact'
    ]; // Order of translatable flags to be checked. Translate on first hit..!

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    public function __construct()
    {
        /**
         * As incoming requests could be for either valid wp users of contact
         * post records, ensure to adjust the $post_type accordingly; so as to
         * fall in line with extended class functionality!
         */
        $this->adjust_global_values_by_incoming_sys_type($this->fetch_incoming_link_param('type'));

        /**
         * Specify metadata structure, specific to the processing of current
         * magic link type.
         *
         * - meta:              Magic link plugin related data.
         *      - app_type:     Flag indicating type to be processed by magic link plugin.
         *      - post_type     Magic link type post type.
         *      - contacts_only:    Boolean flag indicating how magic link type user assignments are to be handled within magic link plugin.
         *                          If True, lookup field to be provided within plugin for contacts only searching.
         *                          If false, Dropdown option to be provided for user, team or group selection.
         *      - fields:       List of fields to be displayed within magic link frontend form.
         *      - field_refreshes:  Support field label updating.
         */
        $this->meta = [
            'app_type'       => 'magic_link',
            'post_type'      => $this->post_type,
            'contacts_only'  => false,
            'supports_create' => true,
            'fields'         => [
                [
                    'id'    => 'name',
                    'label' => ''
                ],
                [
                    'id'    => 'milestones',
                    'label' => ''
                ],
                [
                    'id'    => 'overall_status',
                    'label' => ''
                ],
                [
                    'id'    => 'faith_status',
                    'label' => ''
                ],
                [
                    'id'    => 'contact_phone',
                    'label' => ''
                ],
                [
                    'id'    => 'comments',
                    'label' => __('Comments', 'disciple_tools') // Special Case!
                ],

                [
                    'id'    => 'seeker_path',
                    'label' => __('Seeker Path', 'disciple_tools') // Special Case!
                ],
                [
                    'id'    => 'location_grid_meta', // Special Case!
                    'label' => __('Address / Location', 'disciple_tools')
                ],
            ],
            'fields_refresh' => [
                'enabled'    => true,
                'post_type'  => 'contacts',
                'ignore_ids' => ['comments']
            ]
        ];

        /**
         * Once adjustments have been made, proceed with parent instantiation!
         */
        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        /**
         * user_app and module section
         */
        add_filter('dt_settings_apps_list', [$this, 'dt_settings_apps_list'], 10, 1);
        add_action('rest_api_init', [$this, 'add_endpoints']);

        /**
         * tests if other URL
         */
        $url = dt_get_url_path();
        if (strpos($url, $this->root . '/' . $this->type) === false) {
            return;
        }
        /**
         * tests magic link parts are registered and have valid elements
         */
        if (!$this->check_parts_match()) {
            return;
        }

        // load if valid url
        add_action('dt_blank_body', [$this, 'body']);
        add_filter('dt_magic_url_base_allowed_css', [$this, 'dt_magic_url_base_allowed_css'], 10, 1);
        add_filter('dt_magic_url_base_allowed_js', [$this, 'dt_magic_url_base_allowed_js'], 10, 1);
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts'], 100);
    }

    public function adjust_global_values_by_incoming_sys_type($type)
    {
        if (!empty($type)) {
            switch ($type) {
                case 'wp_user':
                    $this->post_type = 'user';
                    break;
                case 'post':
                    $this->post_type = 'contacts';
                    break;
            }
        }
    }

    public function dt_magic_url_base_allowed_js($allowed_js)
    {
        // @todo add or remove js files with this filter

        $allowed_js[] = 'google-search-widget';
        $allowed_js[] = 'mapbox-gl';
        $allowed_js[] = 'mapbox-cookie';
        $allowed_js[] = 'mapbox-search-widget';
        $allowed_js[] = 'jquery-typehead';
        $allowed_js[] = 'dtwc-form-components';
        $allowed_js[] = 'toastify-js';

        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css($allowed_css)
    {
        // @todo add or remove js files with this filter

        $allowed_css[] = 'mapbox-gl-css';
        $allowed_css[] = 'jquery-typehead-css';
        $allowed_css[] = 'material-font-icons-css';
        $allowed_css[] = 'dtwc-light-css';
        $allowed_css[] = 'toastify-js-css';

        return $allowed_css;
    }

    public function wp_enqueue_scripts()
    {
        if (DT_Mapbox_API::get_key()) {
            DT_Mapbox_API::load_mapbox_header_scripts();
            DT_Mapbox_API::load_mapbox_search_widget();
        }

        wp_enqueue_style('toastify-js-css', 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css', [], '1.12.0');
        wp_enqueue_style('micon', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,1,0', [], '1');
        wp_enqueue_style('dashicons');
        wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js', ['jquery'], '1.12.0');
    }

    /**
     * Builds magic link type settings payload:
     * - key:               Unique magic link type key; which is usually composed of root, type and _magic_key suffix.
     * - url_base:          URL path information to map with parent magic link type.
     * - label:             Magic link type name.
     * - description:       Magic link type description.
     * - settings_display:  Boolean flag which determines if magic link type is to be listed within frontend user profile settings.
     *
     * @param $apps_list
     *
     * @return mixed
     */
    public function dt_settings_apps_list($apps_list)
    {
        $apps_list[$this->meta_key] = [
            'key'              => $this->meta_key,
            'url_base'         => $this->root . '/' . $this->type,
            'label'            => $this->page_title,
            'description'      => $this->page_description,
            'settings_display' => true
        ];

        return $apps_list;
    }

    /**
     * Writes custom styles to header
     *
     * @see DT_Magic_Url_Base()->header_style() for default state
     * @todo remove if not needed
     */
    public function header_style()
    {
?>
        <style>
            body {
                background-color: white;
                padding: 1em;
            }

            #mapPreview {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999999;
                overflow: auto;
                display: none;
            }

            #mapPreview.show {
                display: block;
            }

            #mapPreview .content {
                max-width: calc(100vw - 100px);
                height: calc(100vh - 100px);
                margin-top: 0px;
                margin-left: 50px;
                background: white;
                opacity: 0;
                border-radius: 20px;
                padding: 20px;
                padding-top: 10px;
            }

            #mapPreview.show .content {
                opacity: 1;
                margin-top: 50px;
            }

            #mapPreview .content .header {
                height: 40px;
            }

            #mapPreview .content .header span {
                display: inline-block;
                height: 40px;
                line-height: 40px;
            }

            #mapPreview .content .header button {
                float: right;
                height: 40px;
                width: 45px;
                opacity: .2;
                font-size: 20px;
            }

            #mapPreview .content .header button:hover {
                opacity: 1;
            }

            #mapPreview .content .body .wrap-map {
                height: calc(100vh - 180px);
                border-radius: 10px;
                overflow: hidden;
            }

            #mapPreview .content .body .wrap-map #mpMap {
                height: 100%;
            }

            .api-content-div-style {
                height: 300px;
                overflow-x: hidden;
                overflow-y: scroll;
                text-align: left;
            }

            .api-content-table tbody {
                border: none;
            }

            .api-content-table tr {
                cursor: pointer;
                background: #ffffff;
                padding: 0px;
            }

            .api-content-table tr:hover {
                background-color: #f5f5f5;
            }
        </style>
    <?php
    }

    /**
     * Writes javascript to the header
     *
     * @see DT_Magic_Url_Base()->header_javascript() for default state
     * @todo remove if not needed
     */
    public function header_javascript()
    {
    ?>
        <script></script>
    <?php
    }

    /**
     * Writes javascript to the footer
     *
     * @see DT_Magic_Url_Base()->footer_javascript() for default state
     * @todo remove if not needed
     */
    public function footer_javascript()
    {
    ?>
        <script>
            let jsObject = [<?php echo json_encode([
                                'map_key'                 => DT_Mapbox_API::get_key(),
                                'root'                    => esc_url_raw(rest_url()),
                                'nonce'                   => wp_create_nonce('wp_rest'),
                                'parts'                   => $this->parts,
                                'milestones'              => DT_Posts::get_post_field_settings('contacts')['milestones']['default'],
                                'overall_status'          => DT_Posts::get_post_field_settings('contacts')['overall_status']['default'],
                                'faith_status'            => DT_Posts::get_post_field_settings('contacts')['faith_status']['default'],
                                'seeker_path'             => DT_Posts::get_post_field_settings('contacts')['seeker_path']['default'],
                                'link_obj_id'             => Disciple_Tools_Bulk_Magic_Link_Sender_API::fetch_option_link_obj($this->fetch_incoming_link_param('id')),
                                'sys_type'                => $this->fetch_incoming_link_param('type'),
                                'ekballo_chat_url'                => get_option('ekballo_chat_url'),
                                'translations'            => [
                                    'add' => __('Add Magic', 'disciple-tools-bulk-magic-link-sender'),
                                ],
                                'submit_success_function' => Disciple_Tools_Bulk_Magic_Link_Sender_API::get_link_submission_success_js_code(),
                                'mapbox'        => [
                                    'map_key'        => DT_Mapbox_API::get_key(),
                                    'google_map_key' => Disciple_Tools_Google_Geocode_API::get_key(),
                                    'translations'   => [
                                        'search_location' => __('Search Location', 'disciple_tools'),
                                        'delete_location' => __('Delete Location', 'disciple_tools'),
                                        'use'             => __('Use', 'disciple_tools'),
                                        'open_modal'      => __('Open Modal', 'disciple_tools')
                                    ]
                                ],
                            ]) ?>][0]

            window.d = {
                current_contact: {},
                someother: '',
                deleted_contacts: [],
                default_details: 'a:1:{s:8:"verified";b:0;}',
                lgms: {
                    toDeletes: [],
                    gpsVals: ''
                }
            }

            /**
             * Fetch assigned contacts
             */
            window.get_magic = (searchWord = '') => {
                const payload = {
                    action: 'get',
                    parts: jsObject.parts,
                    sys_type: jsObject.sys_type,
                    ts: moment().unix() // Alter url shape, so as to force cache refresh!
                }

                if (searchWord.length > 0) {
                    payload.search = searchWord
                }

                jQuery.ajax({
                    type: "GET",
                    data: {
                        ...payload
                        //action: 'get',
                        //parts: jsObject.parts
                    },
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce)

                        $('.api-content-table').hide()
                        $('#apiContentLoader').show()
                    }
                }).done(function(data) {
                    $('.api-content-table').show()
                    $('#apiContentLoader').hide()

                    window.load_magic(data)
                }).fail(function(e) {
                    console.error(e)
                    jQuery('#error').html(e)
                    $('.api-content-table').show()
                    $('#apiContentLoader').hide()
                })
            };

            /**
             * Display returned list of assigned contacts
             */
            window.load_magic = (data) => {
                let content = jQuery('#api-content');
                let table = jQuery('.api-content-table');
                let total = jQuery('#total');
                let spinner = jQuery('.loading-spinner');

                // Remove any previous entries
                table.find('tbody').empty()

                // Set total hits count
                total.html(data['total'] ? data['total'] : '0');

                // Iterate over returned posts
                if (data['posts']) {
                    const sortedPosts = data.posts.sort((a, b) => b.created_timestamp - a.created_timestamp)
                    sortedPosts.forEach(v => {

                        const hasFb = v.facebook !== '' ? true : false
                        const fb = {
                            id: hasFb ? v.facebook.page_scoped_ids[0] : ''
                        }

                        let html = `<tr>
                        <td onclick="get_assigned_contact_details('${window.lodash.escape(v.id)}', '${window.lodash.escape(v.name)}');">${window.lodash.escape(v.name)}</td>
                        <td onclick="get_assigned_contact_details('${window.lodash.escape(v.id)}', '${window.lodash.escape(v.name)}');" style="width: 150px;">${window.lodash.escape(v.last_modified)}</td>
						<td style="text-align:center; width: 100px;">
							${ jsObject.ekballo_chat_url !== '' || jsObject.ekballo_chat_url !== false
							?
							(hasFb
								? `<a href="${ hasFb ? jsObject.ekballo_chat_url + '/#/magic_link?psid=' + fb.id : '#' }" target="_blank" style="font-size: 24px;">
									<i class="fi-comments" style="color: rgb(31, 145, 242);"></i>
									</a>`
								: '<i class="fi-comments" style="color: #999; font-size: 24px;"></i>')
							: ''}
						</td>
                        </tr>`;

                        table.find('tbody').append(html);
                    });
                }
            };

            /**
             * Fetch requested contact details
             */
            window.get_contact = (post_id) => {
                let comment_count = 10;

                jQuery('.form-content-table').hide()

                // Dispatch request call
                jQuery.ajax({
                    type: "GET",
                    data: {
                        action: 'get',
                        parts: jsObject.parts,
                        sys_type: jsObject.sys_type,
                        post_id: post_id,
                        comment_count: comment_count,
                        ts: moment().unix() // Alter url shape, so as to force cache refresh!
                    },
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/post',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce);
                        xhr.setRequestHeader('Cache-Control', 'no-store');
                    }
                }).done(function(data) {
                    data['comment_count'] = comment_count;

                    // Was our post fetch request successful...?
                    if (data['success'] && data['post']) {
                        window.generateForm(data)
                    } else {
                        // TODO: Error Msg...!
                        window.generateForm()
                    }

                }).fail(function(e) {
                    console.error(e);
                    jQuery('#error').html(e);
                });
            };

            window.get_phone = (post_id) => {
                // Dispatch request call
                jQuery.ajax({
                    type: "GET",
                    data: {
                        action: 'get',
                        parts: jsObject.parts,
                        sys_type: jsObject.sys_type,
                        post_id: post_id,
                        ts: moment().unix() // Alter url shape, so as to force cache refresh!
                    },
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/get_phone',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce);
                        xhr.setRequestHeader('Cache-Control', 'no-store');
                    }

                }).done(function(data) {
                    window.d.current_contact.contact_phone = [...data]
                    window.renderPhone(post_id, data)
                }).fail(function(e) {
                    console.error(e);
                    jQuery('#error').html(e);
                });
            };

            window.generateForm = (data = null) => {
                let comment_count = 0;
                if (data !== null) {
                    comment_count = data['comment_count'];
                }

                // Display submit button
                jQuery('#content_submit_but').fadeIn('fast');

                // ID
                jQuery('#post_id').val(data === null ? 0 : data['post']['ID']);

                // NAME
                let post_name = window.lodash.escape(data === null ? '' : data['post']['name']);
                jQuery('#contact_name').html(post_name);
                if (window.is_field_enabled('name')) {
                    jQuery('#form_content_name_td').html(`
                  <input id="post_name" type="text" value="${post_name}" />
                  `);
                } else {
                    jQuery('#form_content_name_tr').hide();
                }

                // MILESTONES
                if (window.is_field_enabled('milestones')) {
                    let html_milestones = ``;
                    jQuery.each(jsObject.milestones, function(idx, milestone) {

                        // Determine button selection state
                        let button_select_state = 'empty-select-button';
                        if (data !== null && data['post']['milestones'] && (data['post']['milestones'].indexOf(idx) > -1)) {
                            button_select_state = 'selected-select-button';
                        }

                        // Build button widget
                        html_milestones += `<button id="${window.lodash.escape(idx)}"
                                              type="button"
                                              data-field-key="milestones"
                                              class="dt_multi_select ${button_select_state} button select-button">
                                          <img class="dt-icon" src="${window.lodash.escape(milestone['icon'])}"/>
                                          ${window.lodash.escape(milestone['label'])}
                                      </button>`;
                    });
                    jQuery('#form_content_milestones_td').html(html_milestones);

                    // Respond to milestone button state changes
                    jQuery('.dt_multi_select').on("click", function(evt) {
                        let milestone = jQuery(evt.currentTarget);
                        if (milestone.hasClass('empty-select-button')) {
                            milestone.removeClass('empty-select-button');
                            milestone.addClass('selected-select-button');
                        } else {
                            milestone.removeClass('selected-select-button');
                            milestone.addClass('empty-select-button');
                        }
                    });
                } else {
                    jQuery('#form_content_milestones_tr').hide();
                }

                // OVERALL_STATUS
                if (window.is_field_enabled('overall_status')) {
                    let html_overall_status = `<select id="post_overall_status" class="select-field">`;
                    jQuery.each(jsObject.overall_status, function(idx, overall_status) {

                        // Determine selection state
                        let select_state = '';
                        if (data !== null && data['post']['overall_status'] && (String(data['post']['overall_status']['key']) === String(idx))) {
                            select_state = 'selected';
                        }

                        // Add option
                        html_overall_status += `<option value="${window.lodash.escape(idx)}" ${select_state}>${window.lodash.escape(overall_status['label'])}</option>`;
                    });
                    html_overall_status += `</select>`;
                    jQuery('#form_content_overall_status_td').html(html_overall_status);
                } else {
                    jQuery('#form_content_overall_status_tr').hide();
                }

                // FAITH_STATUS
                if (window.is_field_enabled('faith_status')) {
                    let html_faith_status = `<select id="post_faith_status" class="select-field">`;
                    html_faith_status += `<option value=""></option>`;
                    jQuery.each(jsObject.faith_status, function(idx, faith_status) {

                        // Determine selection state
                        let select_state = '';
                        if (data !== null && data['post']['faith_status'] && (String(data['post']['faith_status']['key']) === String(idx))) {
                            select_state = 'selected';
                        }

                        // Add option
                        html_faith_status += `<option value="${window.lodash.escape(idx)}" ${select_state}>${window.lodash.escape(faith_status['label'])}</option>`;
                    });
                    html_faith_status += `</select>`;
                    jQuery('#form_content_faith_status_td').html(html_faith_status);
                } else {
                    jQuery('#form_content_faith_status_tr').hide();
                }

                // CONTACT_PHONE
                if (window.is_field_enabled('contact_phone')) {
                    window.get_phone(data !== null ? data['post']['ID'] : null)
                } else {
                    jQuery('#form_content_contact_phone_tr').hide();
                }

                // COMMENTS
                if (window.is_field_enabled('comments')) {
                    let counter = 0;
                    let html_comments = `<textarea></textarea><br>`;
                    if (data !== null && data['comments']['comments']) {
                        data['comments']['comments'].forEach(comment => {
                            if (counter++ < comment_count) { // Enforce comment count limit..!
                                html_comments += `<b>${window.lodash.escape(comment['comment_author'])} @ ${window.lodash.escape(comment['comment_date'])}</b><br>`;
                                html_comments += `${window.lodash.escape(comment['comment_content'])}<hr>`;
                            }
                        });
                    }
                    jQuery('#form_content_comments_td').html(html_comments);
                } else {
                    jQuery('#form_content_comments_tr').hide();
                }

                // SEEKER PATH
                const seekerEl = {
                    tr: jQuery('#form_content_seeker_path_tr'),
                    td: jQuery('#form_content_seeker_path_td')
                }

                if (window.is_field_enabled('seeker_path')) {
                    let html_faith_status = `<select id="post_seeker_path" class="select-field">`;
                    html_faith_status += `<option value="">Select Seeker Path</option>`;
                    jQuery.each(jsObject.seeker_path, function(idx, faith_status) {

                        // Determine selection state
                        let select_state = '';
                        if (data !== null && data['post']['seeker_path'] && (String(data['post']['seeker_path']['key']) === String(idx))) {
                            select_state = 'selected';
                        }

                        // Add option
                        html_faith_status += `<option value="${window.lodash.escape(idx)}" ${select_state}>${window.lodash.escape(faith_status['label'])}</option>`;
                    });
                    html_faith_status += `</select>`;
                    seekerEl.td.html(html_faith_status);
                } else {
                    seekerEl.tr.hide()
                }

                // LOCATION / ADDERSS
                const locationEl = {
                    tr: jQuery('#form_content_location_grid_meta_tr'),
                    td: jQuery('#form_content_location_grid_meta_td')
                }

                if (window.is_field_enabled('location_grid_meta')) {
                    const xx = `<div id="location-grid-meta-results"></div>
						<button id="btnAddLocation" style="display: none;">Add Location</button>
						<div class="reveal" id="mapping-modal" data-v-offset="0" data-reveal>
						  <div id="mapping-modal-contents"></div>
						  <button class="close-button" data-close aria-label="Close modal" type="button">
						    <span aria-hidden="true">&times;</span>
						  </button>
						</div>`

                    locationEl.td.find('#mapbox-wrapper').html(xx)

                    // load previous saved locations
                    let lgm_results = locationEl.td.find('#location-grid-meta-results');
                    if (data['post']['location_grid_meta'] !== undefined && data['post']['location_grid_meta'].length !== 0) {
                        jQuery.each(data['post']['location_grid_meta'], function(i, v) {
                            if (v.grid_meta_id) {
                                lgm_results.append(`<div class="input-group">
									<input type="text" class="active-location input-group-field" id="location-${window.lodash.escape(v.grid_meta_id)}" dir="auto" value="${window.lodash.escape(v.label)}" readonly />
										<div class="input-group-button">
											<button
												type="button"
												class="button success delete-button-style open-mapping-grid-modal"
												title="${window.lodash.escape(jsObject['mapbox']['translations']['open_modal'])}"
												data-id="${window.lodash.escape(v.grid_meta_id)}"
											>
												<i class="fi-map"></i>
											</button>

											<button
												type="button"
												class="button alert delete-button-style delete-button mapbox-delete-button"
												title="${window.lodash.escape(jsObject['mapbox']['translations']['delete_location'])}"
												data-id="${window.lodash.escape(v.grid_meta_id)}"
											>
												&times;
											</button>
										</div>
									</div>`);
                            } else {
                                lgm_results.append(`<div class="input-group">
									<input type="text" class="dt-communication-channel input-group-field" id="${window.lodash.escape(v.key)}" value="${window.lodash.escape(v.label)}" dir="auto" data-field="contact_address" />
									<div class="input-group-button">
										<button type="button" class="button success delete-button-style open-mapping-address-modal"
											title="${window.lodash.escape(jsObject['mapbox']['translation']['open_modal'])}"
											data-id="${window.lodash.escape(v.grid_meta_id)}"
											data-field="contact_address"
											data-key="${window.lodash.escape(v.key)}">
												<i class="fi-pencil"></i>
										</button>
										<button
											type="button"
											class="button alert input-height delete-button-style channel-delete-button delete-button"
											title="${window.lodash.escape(jsObject['mapbox']['translations']['delete_location'])}"
											data-id="${window.lodash.escape(v.key)}"
											data-field="contact_address"
											data-key="${window.lodash.escape(v.key)}"
										>
											&times;
										</button>
									</div>
								</div>`);
                            }
                        })
                    }

                    // add
                    $('#btnAddLocation').on('click', function() {
                        console.log('lgm : add')
                        if (locationEl.td.find('#mapbox-autocomplete').length === 0) {
                            $('#mapbox-wrapper').prepend(`
								<div>
									<div id="mapbox-autocomplete" class="mapbox-autocomplete input-group" data-autosubmit="false" style="width: calc(100% - 50px); float: left;">
										<input id="mapbox-search" type="text" name="mapbox_search" placeholder="${window.lodash.escape(jsObject['mapbox']['translations']['search_location'])}" autocomplete="off" dir="auto" />
	                                	<div class="input-group-button">
											<button id="mapbox-spinner-button" class="button hollow" style="display:none;"><span class="loading-spinner active"></span></button>
											<button id="mapbox-spinner-button" class="button hollow"><span class="loading-spinner"></span></button>
											<button
												id="mapbox-clear-autocomplete"
												class="button alert input-height delete-button-style mapbox-delete-button"
												type="button" title="${window.lodash.escape(jsObject['mapbox']['translations']['delete_location'])}" >
												&times;
											</button>
										</div>
										<div id="mapbox-autocomplete-list" class="mapbox-autocomplete-items"></div>
									</div>
									<div style="width: 39px; float:right;">
										<button id="btn_GetLocation" type="button" class="button" style="width: 100%;"><i class="fi-marker"></i></button>
									</div>
									<div style="clear: both;"></div>
								</div>
							`)
                        }
                        write_input_widget()
                    })

                    $('#btnAddLocation').trigger('click')

                    // delete
                    $('.mapbox-delete-button').on('click', evt => {
                        console.log('lgm : delete')
                        const me = $(evt.currentTarget)

                        if (me.data('id') !== 'new') {
                            window.d.lgms.toDeletes.push({
                                grid_meta_id: me.data('id'),
                                delete: true
                            })

                            me.parent().parent().remove()
                        }
                    })

                    // open modal
                    $('.open-mapping-grid-modal').on('click', e => {
                        const dd = {
                            mp: $('#mapPreview'),
                            gmid: $(e.currentTarget).data('id'),
                            lgm: data['post']['location_grid_meta'],
                            zoom: 15
                        }

                        dd.mp.addClass('show')
                        if (dd.lgm !== undefined && dd.lgm.length !== 0) {
                            const gd = dd.lgm.find(lgm => lgm.grid_meta_id === dd.gmid.toString())
                            dd.mp.find('.content .header .title').empty().append(gd.label)

                            if (gd.level === 'admin0') {
                                dd.zoom = 3
                            } else if (gd.level === 'admin1') {
                                dd.zoom = 6
                            } else if (gd.level === 'admin2') {
                                dd.zoom = 10
                            }

                            dd.mp.find('.content .body').find('#mpMap').empty()
                            mapboxgl.accessToken = dtMapbox.map_key

                            const map = new mapboxgl.Map({
                                container: 'mpMap',
                                style: 'mapbox://styles/mapbox/streets-v11',
                                center: [gd.lng, gd.lat],
                                minZoom: 1,
                                zoom: dd.zoom
                            })

                            const marker = new mapboxgl.Marker().setLngLat([gd.lng, gd.lat]).addTo(map)
                        }
                    })

                    $('#btn_GetLocation').on('click', e => {
                        navigator.geolocation.getCurrentPosition(l => {
                            fns.get.reverse_lookup(l.coords.longitude, l.coords.latitude)
                        })
                    })

                } else {
                    locationEl.tr.hide()
                }

                // Display updated post fields
                jQuery('.form-content-table').show();
            }

            /**
             * Determine if field has been enabled
             */
            window.is_field_enabled = (field_id) => {
                // Enabled by default
                let enabled = true;

                // Iterate over type field settings
                if (jsObject.link_obj_id['type_fields']) {
                    jsObject.link_obj_id['type_fields'].forEach(field => {

                        // Is matched field enabled...?
                        if (String(field['id']) === String(field_id)) {
                            enabled = field['enabled'];
                        }
                    });
                }

                return enabled;
            }

            /**
             * Handle fetch request for contact details
             */
            window.get_assigned_contact_details = (post_id, post_name) => {
                let contact_name = jQuery('#contact_name');

                // Update contact name
                contact_name.html(post_name);

                // Fetch requested contact details
                window.get_contact(post_id);
            };

            /**
             * Adjust visuals, based on incoming sys_type
             */
            let assigned_contacts_div = jQuery('#assigned_contacts_div');
            switch (jsObject.sys_type) {
                case 'post':
                    // Bypass contacts list and directly fetch requested contact details
                    assigned_contacts_div.fadeOut('fast');
                    window.get_contact(jsObject.parts.post_id);
                    break;
                default: // wp_user
                    // Fetch assigned contacts for incoming user
                    assigned_contacts_div.fadeIn('fast');
                    window.get_magic();
                    break;
            }

            /**
             * Submit contact details
             */
            jQuery('#content_submit_but').on("click", function() {
                let id = jQuery('#post_id').val();

                // Reset error message field
                let error = jQuery('#error');
                error.html('');

                // Sanity check content prior to submission
                if (!id || String(id).trim().length === 0) {
                    error.html('Invalid post id detected!');

                } else {

                    // Build payload accordingly, based on enabled states
                    let payload = {
                        action: 'get',
                        parts: jsObject.parts,
                        sys_type: jsObject.sys_type,
                        post_id: id
                    }
                    if (window.is_field_enabled('name')) {
                        payload['name'] = String(jQuery('#post_name').val()).trim();
                    }
                    if (window.is_field_enabled('milestones')) {
                        let milestones = [];
                        jQuery('#form_content_milestones_td button').each(function() {
                            milestones.push({
                                'value': jQuery(this).attr('id'),
                                'delete': jQuery(this).hasClass('empty-select-button')
                            });
                        });

                        payload['milestones'] = milestones;
                    }
                    if (window.is_field_enabled('overall_status')) {
                        payload['overall_status'] = String(jQuery('#post_overall_status').val()).trim();
                    }
                    if (window.is_field_enabled('faith_status')) {
                        payload['faith_status'] = String(jQuery('#post_faith_status').val()).trim();
                    }
                    if (window.is_field_enabled('contact_phone')) {
                        const cphone = {
                            deletedPhones: [],
                            newPhones: [],
                            changedPhones: []
                        }

                        $('.wrap-input-phone').each(function(index) {
                            const xx = {
                                meta_id: $(this).data('meta_id'),
                                meta_key: $(this).data('meta_key'),
                                meta_value: $(this).find('.contact_phone_numbers').val()
                            }

                            if ($(this).data('meta_id') === 'new') {
                                xx.random = Math.random().toString(16).substr(2, 3)
                                xx.details = window.d.default_details
                            }

                            cphone[$(this).data('meta_id') === 'new' ? 'newPhones' : 'changedPhones'].push(xx)
                        })

                        window.d.current_contact.contact_phone.forEach(cp => {
                            if (cp.is_deleted === true) {
                                cphone.deletedPhones.push(cp.meta_id)
                            }
                        })

                        payload['contact_phone'] = {
                            ...cphone
                        }
                    }
                    if (window.is_field_enabled('comments')) {
                        payload['comments'] = jQuery('#form_content_comments_td').find('textarea').eq(0).val();
                    }
                    if (window.is_field_enabled('seeker_path')) {
                        if (jQuery('#post_seeker_path').val() !== '') {
                            payload['seeker_path'] = String(jQuery('#post_seeker_path').val()).trim();
                        }
                    }

                    if (window.is_field_enabled('location_grid_meta')) {
                        const slgm = typeof window.selected_location_grid_meta !== 'undefined' ?
                            window.selected_location_grid_meta.location_grid_meta :
                            {
                                values: []
                            }

                        if (typeof window.d.lgms.gpsVals !== 'string') {
                            slgm.values.push(window.d.lgms.gpsVals)
                        }

                        const fmt = {
                            values: [
                                ...slgm.values,
                                ...window.d.lgms.toDeletes
                            ]
                        }

                        console.log(fmt)

                        payload['location_grid_meta'] = fmt
                    }

                    // Submit data for post update
                    jQuery('#content_submit_but').prop('disabled', true);

                    console.log(payload)

                    jQuery.ajax({
                        type: "GET",
                        data: payload,
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/update',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce)
                        }
                    }).done(function(data) {
                        // If successful, refresh page, otherwise; display error message

                        if (data['success']) {
                            Function(jsObject.submit_success_function)();
                            $('#content_submit_but').prop('disabled', false)
                            window.load_magic(data.post)
                            window.get_contact(payload.post_id)
                        } else {
                            jQuery('#error').html(data['message']);
                            jQuery('#content_submit_but').prop('disabled', false);
                        }

                    }).fail(function(e) {
                        console.error(e);
                        jQuery('#error').html(e);
                        jQuery('#content_submit_but').prop('disabled', false);
                    });
                }
            });

            window.fns = {
                search: {
                    show() {
                        $('#wrapSearchToggle')
                        $('#wrapSearchInput')
                        $('#spnSearch')
                        $('#spnCancel')
                        $('#txtSearch')

                        if (this.d.shown) {
                            $('#spnSearch').show()
                            $('#spnCancel').hide()
                            $('#wrapSearchInput').hide()

                            if ($('#txtSearch').val() !== '') {
                                window.get_magic()
                            }


                            $('#txtSearch').val('')
                            this.d.shown = false
                        } else {
                            $('#spnSearch').hide()
                            $('#spnCancel').show()
                            $('#wrapSearchInput').show()

                            this.d.shown = true
                        }
                    },

                    hide() {},
                    d: {
                        shown: false
                    }
                },

                get: {
                    reverse_lookup(lng, lat) {
                        jQuery.ajax({
                            type: "GET",
                            data: {
                                action: 'get',
                                parts: jsObject.parts,
                                sys_type: jsObject.sys_type,
                                //post_id: pid,
                                lng: lng,
                                lat: lat,
                                ts: moment().unix() // Alter url shape, so as to force cache refresh!
                            },
                            contentType: "application/json; charset=utf-8",
                            dataType: "json",
                            url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/get_reverse_lookup',
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce)
                            }
                        }).done(function(res) {
                            $('#mapbox-search').val(res.features[0].place_name)

                            window.d.lgms.gpsVals = {
                                label: res.features[0].place_name,
                                lat: lat,
                                lng: lng,
                                source: 'user',
                            }
                        }).fail(function(e) {
                            console.error(e);
                            jQuery('#error').html(e);
                            jQuery('#content_submit_but').prop('disabled', false);
                        });
                    }
                }
            }

            window.renderPhone = (post_id, data) => {
                let ph_html = '';

                data.forEach(phone => {
                    if (!phone.meta_key.includes('details')) {
                        const idx = data.findIndex(p => p.meta_id === phone.meta_id)
                        const details = data[data.findIndex(p => p.meta_key.match(phone.meta_key) && p.meta_key.includes('details'))]
                        ph_html += `<div class="wrap-input-phone" data-idx="${idx}" data-meta_id="${phone.meta_id}" data-meta_key="${phone.meta_key}" data-meta_details="${details.meta_value}" data-meta_detail_id="${details.meta_id}"><div style="width: ${idx > 0 ? 'calc(100% - 50px)' : '100%'}; display: inline-block;"><input type="text" class="contact_phone_numbers" value="${phone.meta_value}" /></div>${idx > 0 ? `<div class="contact-phone-remover" style="display: inline-block; width: 50px; text-align: center; cursor: pointer;" data-idx="${idx}">x</div>` : ''}</div>`;
                    }
                });

                jQuery('#form_content_contact_phone_td').html(data.length > 0 ? ph_html : '<div class="wrap-input-phone" data-idx="0" data-meta_id="new" data-meta_key="new" deta-meta_details="new"><input type="text" class="contact_phone_numbers" value="" placeholder="Contact Phone Number" /></div>');
                jQuery('#form_content_contact_phone_td').append('<div><button type="button" class="button" id="btn_AddNewPhoneNumber">+</button></div>')

                $('#btn_AddNewPhoneNumber').on('click', function(e) {
                    const lastIdx = $('.wrap-input-phone').length + 1
                    $('#btn_AddNewPhoneNumber').before(`<div class="wrap-input-phone" data-idx="${lastIdx}" data-meta_id="new" data-meta_key="new" data-meta_details="new"><div style="width: calc(100% - 50px); display: inline-block;"><input type="text" class="contact_phone_numbers" class="contact_phone_numbers" value="" placeholder="Contact Phone Number" /></div><div class="contact-phone-remover" style="display: inline-block; width: 50px; text-align: center; cursor: pointer;" data-idx="${lastIdx}">x</div></div>`)

                    $('.contact-phone-remover').on('click', function() {
                        const d = $(`.wrap-input-phone[data-idx=${$(this).data('idx')}]`)
                        const xx = window.d.current_contact.contact_phone.find(cp => cp.meta_id === d.data('meta_id'))

                        if (d.data('meta_id') !== 'new') {
                            window.d.deleted_contacts.push(d.data('meta_id'))
                            window.d.deleted_contacts.push(d.data('meta_detail_id'))
                        }

                        d.remove()
                    });
                });

                $('.contact-phone-remover').on('click', function() {
                    const d = $(`.wrap-input-phone[data-idx=${$(this).data('idx')}]`)
                    window.d.current_contact.contact_phone.find(cp => cp.meta_id === d.data('meta_id').toString()).is_deleted = true
                    window.d.current_contact.contact_phone.find(cp => cp.meta_id === d.data('meta_detail_id').toString()).is_deleted = true

                    if (d.data('meta_id') !== 'new') {
                        window.d.deleted_contacts.push(d.data('meta_id'))
                        window.d.deleted_contacts.push(d.data('meta_detail_id'))
                    }

                    d.remove()
                });
            }

            $('#txtSearch').on('keypress', function(e) {
                if (e.which === 13) {
                    window.get_magic($('#txtSearch').val())
                }
            });

            $('#add_new').on('click', function(e) {
                window.get_contact(0);
            });
        </script>
    <?php
        return true;
    }

    public function body()
    {
        // Revert back to dt translations
        $this->hard_switch_to_default_dt_text_domain();
        $link_obj = Disciple_Tools_Bulk_Magic_Link_Sender_API::fetch_option_link_obj($this->fetch_incoming_link_param('id'));

    ?>
        <div id="mapPreview">
            <div class="content">
                <div class="header">
                    <span class="title"></span>
                    <button type="button" class="btn-close" onclick="jQuery('#mapPreview').removeClass('show')">&times;</button>
                </div>
                <div class="body">
                    <div class="wrap-map">
                        <div id="mpMap"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="custom-style"></div>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell center">
                    <h2 id="title"><b><?php esc_html_e('Updates Needed', 'disciple_tools') ?></b></h2>
                </div>
            </div>
            <hr>
            <div id="content">
                <div id="assigned_contacts_div" style="display: none;">
                    <div>
                        <div style="width: calc(100% - 37px); float:left; display: inline-block;">
                            <h3><?php esc_html_e("Contacts", 'disciple_tools') ?> [ <span id="total">0</span> ]</h3>
                        </div>

                        <!-- Search Toggle -->
                        <div id="wrapSearchToggle" style="float:right; width: 36px; height: 36px; line-height: 36px; text-align: center;" onclick="fns.search.show()">
                            <span id="spnSearch"><i class="fi-magnifying-glass" style="color: #777;"></i></span>
                            <span id="spnCancel" style="display:none;"><i class="fi-x" style="color: red;"></i></span>
                        </div>
                        <div style="clear:both;"></div>
                        <div id="wrapSearchInput" style="padding: 10px; display:none;">
                            <input type="text" id="txtSearch" placeholder="Group name ..." />
                        </div>
                    </div>
                    <hr>
                    <div class="grid-x api-content-div-style" id="api-content">
                        <table class="api-content-table">
                            <thead style="border: 0px solid transparent;">
                                <tr style="border: 0px solid transparent; border-bottom: 1px solid #ddd;">
                                    <td>Name</td>
                                    <td width="150">Last Modified</td>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>

                        <div id="apiContentLoader" style="position: absolute; top: 0; bottom: 0; left:0; right: 0; background: rgba(255,255,255,0.9); text-align: center; line-height: 300px;">
                            Updating
                        </div>

                    </div>
                    <br>
                </div>

                <!-- btn add new -->
                <?php if (isset($link_obj) && property_exists($link_obj, 'type_config') && property_exists($link_obj->type_config, 'supports_create') && $link_obj->type_config->supports_create) : ?>
                    <button id="add_new" class="button select-button">
                        <?php esc_html_e("Add New", 'disciple_tools') ?>
                    </button>
                <?php endif; ?> <!-- e.o btn add new -->

                <!-- ERROR MESSAGES -->
                <span id="error" style="color: red;"></span>
                <br>
                <br>

                <h3><span id="contact_name"></span>
                </h3>
                <hr>
                <div class="grid-x" id="form-content">
                    <input id="post_id" type="hidden" />
                    <?php
                    $field_settings = DT_Posts::get_post_field_settings('contacts', false);
                    ?>
                    <table style="display: none;" class="form-content-table">
                        <tbody>
                            <tr id="form_content_name_tr">
                                <td style="vertical-align: top;">
                                    <b><?php echo esc_attr($field_settings['name']['name']); ?></b>
                                </td>
                                <td id="form_content_name_td"></td>
                            </tr>

                            <tr id="form_content_milestones_tr">
                                <td style="vertical-align: top;">
                                    <b><?php echo esc_attr($field_settings['milestones']['name']); ?></b>
                                </td>
                                <td id="form_content_milestones_td"></td>
                            </tr>

                            <tr id="form_content_overall_status_tr">
                                <td style="vertical-align: top;">
                                    <b><?php echo esc_attr($field_settings['overall_status']['name']); ?></b>
                                </td>
                                <td id="form_content_overall_status_td"></td>
                            </tr>

                            <tr id="form_content_faith_status_tr">
                                <td style="vertical-align: top;">
                                    <b><?php echo esc_attr($field_settings['faith_status']['name']); ?></b>
                                </td>
                                <td id="form_content_faith_status_td"></td>
                            </tr>

                            <tr id="form_content_seeker_path_tr">
                                <td style="vertical-align: top;">
                                    <b><? echo esc_attr($field_settings['seeker_path']['name']); ?></b>
                                </td>
                                <td id="form_content_seeker_path_td" />
                            </tr>

                            <tr id="form_content_location_grid_meta_tr">
                                <td style="vertical-align: top;">
                                    <b><? echo esc_attr($field_settings['location_grid_meta']['name']); ?></b>
                                </td>
                                <td id="form_content_location_grid_meta_td">
                                    <div id="mapbox-wrapper" />
                                </td>
                            </tr>

                            <tr id="form_content_contact_phone_tr">
                                <td style="vertical-align: top;">
                                    <b><?php echo esc_attr($field_settings['contact_phone']['name']); ?></b>
                                </td>
                                <td id="form_content_contact_phone_td"></td>
                            </tr>

                            <tr id="form_content_comments_tr">
                                <td style="vertical-align: top;">
                                    <b><?php esc_html_e("Comments", 'disciple_tools') ?></b>
                                </td>
                                <td id="form_content_comments_td"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <br>

                <!-- SUBMIT UPDATES -->
                <button id="content_submit_but" style="display: none; min-width: 100%;" class="button select-button">
                    <?php esc_html_e("Submit Update", 'disciple_tools') ?>
                </button>
            </div>
        </div>
<?php
    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_endpoints()
    {
        $namespace = $this->root . '/v1';

        // GET
        register_rest_route(
            $namespace,
            '/' . $this->type,
            [
                [
                    'methods'             => "GET",
                    'callback'            => [$this, 'endpoint_get'],
                    'permission_callback' => function (WP_REST_Request $request) {
                        $magic = new DT_Magic_URL($this->root);

                        return $magic->verify_rest_endpoint_permissions_on_post($request);
                    },
                ],
            ]
        );

        // POST
        register_rest_route(
            $namespace,
            '/' . $this->type . '/post',
            [
                [
                    'methods'             => "GET",
                    'callback'            => [$this, 'get_post'],
                    'permission_callback' => function (WP_REST_Request $request) {
                        $magic = new DT_Magic_URL($this->root);

                        /**
                         * Adjust global values accordingly, so as to accommodate both wp_user
                         * and post requests.
                         */
                        $this->adjust_global_values_by_incoming_sys_type($request->get_params()['sys_type']);

                        return $magic->verify_rest_endpoint_permissions_on_post($request);
                    },
                ],
            ]
        );

        // UPDATE
        register_rest_route(
            $namespace,
            '/' . $this->type . '/update',
            [
                [
                    'methods'             => "GET",
                    'callback'            => [$this, 'update_record'],
                    'permission_callback' => function (WP_REST_Request $request) {
                        $magic = new DT_Magic_URL($this->root);

                        /**
                         * Adjust global values accordingly, so as to accommodate both wp_user
                         * and post requests.
                         */
                        $this->adjust_global_values_by_incoming_sys_type($request->get_params()['sys_type']);

                        return $magic->verify_rest_endpoint_permissions_on_post($request);
                    },
                ],
            ]
        );

        // GET PHONE
        register_rest_route(
            $namespace,
            '/' . $this->type . '/get_phone',
            [
                [
                    'methods'             => "GET",
                    'callback'            => [$this, 'get_phone'],
                    'permission_callback' => function (WP_REST_Request $request) {
                        $magic = new DT_Magic_URL($this->root);

                        /**
                         * Adjust global values accordingly, so as to accommodate both wp_user
                         * and post requests.
                         */
                        $this->adjust_global_values_by_incoming_sys_type($request->get_params()['sys_type']);

                        return $magic->verify_rest_endpoint_permissions_on_post($request);
                    },
                ],
            ]
        );

        // GET Reverse Lookup
        register_rest_route(
            $namespace,
            '/' . $this->type . '/get_reverse_lookup',
            [
                [
                    'methods'             => "GET",
                    'callback'            => [$this, 'get_reverse_lookup'],
                    'permission_callback' => function (WP_REST_Request $request) {
                        $magic = new DT_Magic_URL($this->root);

                        /**
                         * Adjust global values accordingly, so as to accommodate both wp_user
                         * and post requests.
                         */
                        $this->adjust_global_values_by_incoming_sys_type($request->get_params()['sys_type']);

                        return $magic->verify_rest_endpoint_permissions_on_post($request);
                    },
                ],
            ]
        );
    }

    public function endpoint_get(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (!isset($params['parts'], $params['action'])) {
            return new WP_Error(__METHOD__, "Missing parameters", ['status' => 400]);
        }

        // Sanitize and fetch user id
        $params  = dt_recursive_sanitize_array($params);
        $user_id = $params["parts"]["post_id"];

        // Fetch all assigned posts
        $data = [];
        if (!empty($user_id)) {

            // Update logged-in user state as required
            $original_user = wp_get_current_user();
            wp_set_current_user($user_id);

            $options = [
                'limit'  => 1000,
                'fields' => [
                    [
                        'assigned_to' => ['me'],
                        "subassigned" => ['me']
                    ],
                    "overall_status" => [
                        "new",
                        "unassigned",
                        "assigned",
                        "active",
                        "from_facebook"
                    ]
                ]
            ];

            if (isset($params['search'])) {
                $options['text'] = $params['search'];
            }

            // Fetch all assigned posts
            $posts = DT_Posts::list_posts('contacts', $options);

            // Revert to original user
            if (!empty($original_user) && isset($original_user->ID)) {
                wp_set_current_user($original_user->ID);
            }

            // Iterate and return valid posts
            if (!empty($posts) && isset($posts['posts'], $posts['total'])) {
                $data['total'] = $posts['total'];
                foreach ($posts['posts'] ?? [] as $post) {
                    $data['posts'][] = [
                        'id'   => $post['ID'],
                        'name' => $post['name'],
                        'last_modified' => $post['last_modified']['formatted'],
                        'facebook' => isset($post['facebook_data']) ? $post['facebook_data'] : '',
                        'created_timestamp' => $post['post_date']['timestamp']
                    ];
                }
            }
        }

        return $data;
    }

    public function get_post(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (!isset($params['post_id'], $params['parts'], $params['action'], $params['comment_count'], $params['sys_type'])) {
            return new WP_Error(__METHOD__, "Missing parameters", ['status' => 400]);
        }

        // Sanitize and fetch user/post id
        $params = dt_recursive_sanitize_array($params);

        // Update logged-in user state if required accordingly, based on their sys_type
        if (!is_user_logged_in()) {
            $this->update_user_logged_in_state($params['sys_type'], $params["parts"]["post_id"]);
        }

        // Fetch corresponding contacts post record
        $response = [];
        $post     = DT_Posts::get_post('contacts', $params['post_id'], false);
        if (!empty($post) && !is_wp_error($post)) {
            $response['success']  = true;
            $response['post']     = $post;
            $response['comments'] = DT_Posts::get_post_comments('contacts', $params['post_id'], false, 'all', ['number' => $params['comment_count']]);
        } else {
            $response['success'] = false;
        }

        return $response;
    }

    public function update_record(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (!isset($params['post_id'], $params['parts'], $params['action'], $params['sys_type'])) {
            return new WP_Error(__METHOD__, "Missing core parameters", ['status' => 400]);
        }

        // Sanitize and fetch user id
        $params = dt_recursive_sanitize_array($params);

        // Update logged-in user state if required accordingly, based on their sys_type
        if (!is_user_logged_in()) {
            $this->update_user_logged_in_state($params['sys_type'], $params["parts"]["post_id"]);
        }

        // Capture name, if present
        $updates = [];
        if (isset($params['name']) && !empty($params['name'])) {
            $updates['name'] = $params['name'];
        }

        // Capture overall status
        if (isset($params['overall_status']) && !empty($params['overall_status'])) {
            $updates['overall_status'] = $params['overall_status'];
        }

        // Capture faith status
        if (isset($params['faith_status'])) {
            $updates['faith_status'] = $params['faith_status'];
        }

        // Capture seeker path
        if (isset($params['seeker_path'])) {
            $updates['seeker_path'] = $params['seeker_path'];
        }

        // Capture location grid id
        if (isset($params['location_grid_meta'])) {
            $locations = [];
            $lgm = $params['location_grid_meta']['location_grid_meta'];
            $updates['location_grid_meta'] = $params['location_grid_meta'];
        }

        // Capture milestones
        if (isset($params['milestones'])) {
            $milestones = [];
            foreach ($params['milestones'] ?? [] as $milestone) {
                $entry          = [];
                $entry['value'] = $milestone['value'];
                if (strtolower(trim($milestone['delete'])) === 'true') {
                    $entry['delete'] = true;
                }
                $milestones[] = $entry;
            }
            if (!empty($milestones)) {
                $updates['milestones'] = [
                    'values' => $milestones
                ];
            }
        }

        // Update specified post record
        if ((int)$params['post_id'] !== 0) {
            $updated_post = DT_Posts::update_post('contacts', $params['post_id'], $updates, false, false);
            if (empty($updated_post) || is_wp_error($updated_post)) {
                return [
                    'success' => false,
                    'post_id' => gettype($params['post_id']),
                    'message' => 'Unable to update contact record details!'
                ];
            }
        } else {
            $updates['type'] = 'access';
            $updated_post = DT_Posts::create_post('contacts', $updates, false, false);

            if (empty($updated_post) || is_wp_error($updated_post)) {
                return [
                    'success' => false,
                    'message' => 'Unable to create contact record!'
                ];
            }
        }

        // Capture contact phone
        if (isset($params['contact_phone']) && !empty($params['contact_phone'])) {
            global $wpdb;

            // delete
            foreach ($params['contact_phone']['deletedPhones'] as $dp) {
                $wpdb->delete($wpdb->prefix . 'postmeta', ['meta_id' => (int)$dp]);
            }

            // change
            foreach ($params['contact_phone']['changedPhones'] as $cp) {

                $wpdb->update(
                    $wpdb->prefix . 'postmeta',
                    ['meta_value' => $cp['meta_value']],
                    ['meta_id' => $cp['meta_id']]
                );
            }

            // add
            foreach ($params['contact_phone']['newPhones'] as $np) {
                $wpdb->insert(
                    $wpdb->prefix . 'postmeta',
                    array(
                        'post_id' => $updated_post['ID'],
                        'meta_key' => 'contact_phone_' . $np['random'],
                        'meta_value' => $np['meta_value']
                    )
                );

                $wpdb->insert(
                    $wpdb->prefix . 'postmeta',
                    array(
                        'post_id' => $updated_post['ID'],
                        'meta_key' => 'contact_phone_' . $np['random'] . '_details',
                        'meta_value' => $np['details']
                    )
                );
            }
        }

        // Add any available comments
        if (isset($params['comments']) && !empty($params['comments'])) {
            $updated_comment = DT_Posts::add_post_comment($updated_post['post_type'], $updated_post['ID'], $params['comments'], 'comment', [], false);
            if (empty($updated_comment) || is_wp_error($updated_comment)) {
                return [
                    'success' => false,
                    'message' => 'Unable to add comment to contact record details!'
                ];
            }
        }

        $post = self::endpoint_get($request);

        // Finally, return successful response
        return [
            'success' => true,
            'post' => $post,
            'message' => 'Successfully ' . (int)$params['post_id'] !== 0 ? 'Updated' : 'Created' . ' Contact Detail.'
        ];
    }

    public function get_phone(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (!isset($params['post_id'], $params['parts'], $params['action'])) {
            return new WP_Error(__METHOD__, "Missing parameters", ['status' => 400]);
        }

        $params = dt_recursive_sanitize_array($params);
        if (!is_user_logged_in()) {
            $this->update_user_logged_in_state($params['sys_type'], $params["parts"]["post_id"]);
        }

        global $wpdb;
        $wpdb->post_id = $params["post_id"];
        return $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE post_id = $wpdb->post_id AND meta_key LIKE 'contact_phone%';", ARRAY_A);
    }

    public function update_user_logged_in_state($sys_type, $user_id)
    {
        switch (strtolower(trim($sys_type))) {
            case 'post':
                wp_set_current_user(0);
                $current_user = wp_get_current_user();
                $current_user->add_cap("magic_link");
                $current_user->display_name = __('Smart Link Submission', 'disciple_tools');
                break;
            default: // wp_user
                wp_set_current_user($user_id);
                break;
        }
    }

    public function get_reverse_lookup(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (!isset($params['lat'], $params['lng'], $params['parts'], $params['action'])) {
            return new WP_Error(__METHOD__, "Missing parameters", ['status' => 400]);
        }

        $params = dt_recursive_sanitize_array($params);
        if (!is_user_logged_in()) {
            $this->update_user_logged_in_state($params['sys_type'], $params["parts"]["post_id"]);
        }

        return DT_Mapbox_API::reverse_lookup($params['lng'], $params['lat']);
    }
}

Disciple_Tools_Magic_Links_Magic_User_App::instance();
