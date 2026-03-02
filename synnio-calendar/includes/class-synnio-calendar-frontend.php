<?php
/**
 * Synnio Calendar Frontend Class
 *
 * Verwaltet alle Frontend-Funktionen und Shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_Frontend {

    /**
     * Flag ob Assets bereits geladen wurden
     */
    private static $assets_enqueued = false;

    /**
     * Konstruktor
     */
    public function __construct() {
        add_shortcode('synnio_calendar', array($this, 'render_calendar_shortcode'));

        // Assets immer laden wenn Shortcode auf Seite ist
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Assets laden wenn Shortcode vorhanden
     */
    public function maybe_enqueue_assets() {
        global $post;

        // Pruefen ob Shortcode auf der Seite ist
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'synnio_calendar')) {
            $this->enqueue_assets();
        }
    }

    /**
     * Assets einbinden
     */
    public function enqueue_assets() {
        if (self::$assets_enqueued) {
            return;
        }

        wp_enqueue_style(
            'synnio-kalender-app-styles',
            SYNNIO_CALENDAR_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SYNNIO_CALENDAR_VERSION
        );

        wp_enqueue_style(
            'font-awesome-synnio-cal',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0'
        );

        wp_enqueue_script(
            'synnio-kalender-app-script',
            SYNNIO_CALENDAR_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SYNNIO_CALENDAR_VERSION,
            true
        );

        $current_user_id = get_current_user_id();

        // Oeffnungszeiten laden
        $bh_raw = get_user_meta($current_user_id, 'synnio_calendar_business_hours', true);
        $business_hours = $bh_raw ? json_decode($bh_raw, true) : null;
        if (!$business_hours) {
            $default_start = get_option('synnio_calendar_working_hours_start', '08:00');
            $default_end = get_option('synnio_calendar_working_hours_end', '18:00');
            $business_hours = array();
            for ($d = 0; $d < 7; $d++) {
                $business_hours[$d] = array(
                    'enabled' => ($d >= 1 && $d <= 5),
                    'start' => $default_start,
                    'end' => $default_end,
                );
            }
        }

        // Pausenzeiten laden
        $breaks_raw = get_user_meta($current_user_id, 'synnio_calendar_breaks', true);
        $breaks = $breaks_raw ? json_decode($breaks_raw, true) : array();

        // Kalender-Anzeigebereich laden
        $display_start = get_user_meta($current_user_id, 'synnio_calendar_display_start', true);
        $display_end = get_user_meta($current_user_id, 'synnio_calendar_display_end', true);
        if (!$display_start) $display_start = '07:00';
        if (!$display_end) $display_end = '20:00';

        wp_localize_script('synnio-kalender-app-script', 'synnioCalendar', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('synnio/v1/calendar/'),
            'nonce' => wp_create_nonce('synnio_calendar_nonce'),
            'userId' => $current_user_id,
            'businessHours' => $business_hours,
            'breaks' => $breaks,
            'displayStart' => $display_start,
            'displayEnd' => $display_end,
            'eventTypes' => Synnio_Calendar::get_event_types($current_user_id),
            'i18n' => array(
                'today' => __('Heute', 'synnio-calendar'),
                'week' => __('Woche', 'synnio-calendar'),
                'month' => __('Monat', 'synnio-calendar'),
                'day' => __('Tag', 'synnio-calendar'),
                'agenda' => __('Agenda', 'synnio-calendar'),
                'newEvent' => __('Neuer Termin', 'synnio-calendar'),
                'save' => __('Speichern', 'synnio-calendar'),
                'cancel' => __('Abbrechen', 'synnio-calendar'),
                'delete' => __('Loeschen', 'synnio-calendar'),
                'edit' => __('Bearbeiten', 'synnio-calendar'),
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Termin loeschen moechten?', 'synnio-calendar'),
                'errorLoading' => __('Fehler beim Laden der Termine', 'synnio-calendar'),
                'errorSaving' => __('Fehler beim Speichern', 'synnio-calendar'),
                'moduleNotActivated' => __('Kalendermodul nicht freigeschalten', 'synnio-calendar'),
                'days' => array(
                    __('Sonntag', 'synnio-calendar'),
                    __('Montag', 'synnio-calendar'),
                    __('Dienstag', 'synnio-calendar'),
                    __('Mittwoch', 'synnio-calendar'),
                    __('Donnerstag', 'synnio-calendar'),
                    __('Freitag', 'synnio-calendar'),
                    __('Samstag', 'synnio-calendar'),
                ),
                'daysShort' => array(
                    __('So', 'synnio-calendar'),
                    __('Mo', 'synnio-calendar'),
                    __('Di', 'synnio-calendar'),
                    __('Mi', 'synnio-calendar'),
                    __('Do', 'synnio-calendar'),
                    __('Fr', 'synnio-calendar'),
                    __('Sa', 'synnio-calendar'),
                ),
                'months' => array(
                    __('Januar', 'synnio-calendar'),
                    __('Februar', 'synnio-calendar'),
                    __('Maerz', 'synnio-calendar'),
                    __('April', 'synnio-calendar'),
                    __('Mai', 'synnio-calendar'),
                    __('Juni', 'synnio-calendar'),
                    __('Juli', 'synnio-calendar'),
                    __('August', 'synnio-calendar'),
                    __('September', 'synnio-calendar'),
                    __('Oktober', 'synnio-calendar'),
                    __('November', 'synnio-calendar'),
                    __('Dezember', 'synnio-calendar'),
                ),
            ),
        ));

        self::$assets_enqueued = true;
    }

    /**
     * Kalender-Shortcode rendern
     *
     * @param array $atts Shortcode-Attribute
     * @return string HTML-Ausgabe
     */
    public function render_calendar_shortcode($atts) {
        // Assets sicherstellen (fuer Widget-Bereiche etc.)
        $this->enqueue_assets();
        $atts = shortcode_atts(array(
            'view' => get_option('synnio_calendar_default_view', 'week'),
            'user_id' => null,
            'readonly' => 'false',
            'show_sidebar' => 'true',
            'show_right_panel' => 'true',
            'height' => '100vh',
        ), $atts, 'synnio_calendar');

        // Benutzer ermitteln
        $user_id = $atts['user_id'] ? intval($atts['user_id']) : get_current_user_id();

        // Pruefen ob der Benutzer angemeldet ist
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        // Pruefen ob der Kalender fuer den Benutzer freigeschaltet ist
        if (!Synnio_Calendar::is_calendar_enabled_for_user($user_id)) {
            return $this->render_module_not_activated();
        }

        // Kalender-Daten vorbereiten
        $db = synnio_calendar()->db;
        $calendars = $db->get_user_calendars($user_id);
        $statistics = $db->get_user_statistics($user_id);

        // Falls keine Kalender existieren, Standard-Kalender erstellen
        if (empty($calendars)) {
            $db->create_default_calendars_for_user($user_id);
            $calendars = $db->get_user_calendars($user_id);
        }

        $readonly = filter_var($atts['readonly'], FILTER_VALIDATE_BOOLEAN);
        $show_sidebar = filter_var($atts['show_sidebar'], FILTER_VALIDATE_BOOLEAN);
        $show_right_panel = filter_var($atts['show_right_panel'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        ?>
        <div id="synnio-calendar-wrapper">
        <div class="synnio-calendar-app" data-user-id="<?php echo esc_attr($user_id); ?>" data-view="<?php echo esc_attr($atts['view']); ?>" data-readonly="<?php echo esc_attr($readonly ? 'true' : 'false'); ?>" style="height: <?php echo esc_attr($atts['height']); ?>;">

            <?php if ($show_sidebar) : ?>
            <!-- Sidebar -->
            <aside class="synnio-sidebar">
                <div class="synnio-sidebar-header">
                    <div class="synnio-logo">
                        <div class="synnio-logo-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <span>Synnio Kalender</span>
                    </div>
                    <?php if (!$readonly) : ?>
                    <button class="synnio-btn-new-event" id="synnioNewEventBtn">
                        <i class="fas fa-plus"></i>
                        <?php _e('Neuer Termin', 'synnio-calendar'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Mini Calendar -->
                <div class="synnio-mini-calendar">
                    <div class="synnio-mini-cal-header">
                        <span class="synnio-mini-cal-title" id="synnioMiniCalTitle"></span>
                        <div class="synnio-mini-cal-nav">
                            <button id="synnioMiniCalPrev"><i class="fas fa-chevron-left"></i></button>
                            <button id="synnioMiniCalNext"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="synnio-mini-cal-grid" id="synnioMiniCalGrid">
                        <!-- Mini Calendar Grid wird per JS generiert -->
                    </div>
                </div>

                <!-- Calendar Types -->
                <div class="synnio-calendar-types">
                    <div class="synnio-section-title"><?php _e('Meine Kalender', 'synnio-calendar'); ?></div>
                    <?php foreach ($calendars as $calendar) : ?>
                    <div class="synnio-calendar-type-item" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>">
                        <input type="checkbox" class="synnio-calendar-toggle" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>" <?php checked($calendar['is_visible'], 1); ?>>
                        <span class="synnio-calendar-color" style="background: <?php echo esc_attr($calendar['color']); ?>"></span>
                        <span class="synnio-calendar-type-name" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>"><?php echo esc_html($calendar['name']); ?></span>
                        <span class="synnio-calendar-type-count" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>">0</span>
                        <?php if (!$readonly) : ?>
                        <button class="synnio-calendar-edit-btn" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>" data-calendar-name="<?php echo esc_attr($calendar['name']); ?>" data-calendar-color="<?php echo esc_attr($calendar['color']); ?>" data-calendar-is-default="<?php echo esc_attr($calendar['is_default']); ?>" title="<?php _e('Kalender bearbeiten', 'synnio-calendar'); ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$readonly) : ?>
                    <button class="synnio-add-calendar-btn" id="synnioAddCalendarBtn">
                        <i class="fas fa-plus"></i>
                        <?php _e('Neuer Kalender', 'synnio-calendar'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <div class="synnio-sidebar-footer">
                    <?php if (!$readonly) : ?>
                    <button class="synnio-sidebar-footer-btn" id="synnioBatchBtn">
                        <i class="fas fa-layer-group"></i>
                        <?php _e('Sammelverarbeitung', 'synnio-calendar'); ?>
                    </button>
                    <?php endif; ?>
                    <button class="synnio-sidebar-footer-btn" id="synnioSettingsBtn">
                        <i class="fas fa-cog"></i>
                        <?php _e('Einstellungen', 'synnio-calendar'); ?>
                    </button>
                </div>
            </aside>
            <?php endif; ?>

            <!-- Main Content -->
            <main class="synnio-main-content">
                <!-- Header -->
                <header class="synnio-main-header">
                    <div class="synnio-header-left">
                        <button class="synnio-btn-today" id="synnioTodayBtn"><?php _e('Heute', 'synnio-calendar'); ?></button>
                        <div class="synnio-nav-arrows">
                            <button id="synnioPrevBtn"><i class="fas fa-chevron-left"></i></button>
                            <button id="synnioNextBtn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <span class="synnio-current-date" id="synnioCurrentDate"></span>
                    </div>
                    <div class="synnio-header-center">
                        <button class="synnio-view-btn" data-view="day"><?php _e('Tag', 'synnio-calendar'); ?></button>
                        <button class="synnio-view-btn" data-view="week"><?php _e('Woche', 'synnio-calendar'); ?></button>
                        <button class="synnio-view-btn" data-view="month"><?php _e('Monat', 'synnio-calendar'); ?></button>
                        <button class="synnio-view-btn" data-view="agenda"><?php _e('Agenda', 'synnio-calendar'); ?></button>
                    </div>
                    <div class="synnio-header-right">
                        <div class="synnio-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="synnioSearchInput" placeholder="<?php _e('Termine suchen...', 'synnio-calendar'); ?>">
                        </div>
                        <button class="synnio-icon-btn" id="synnioNotificationsBtn">
                            <i class="fas fa-bell"></i>
                            <span class="synnio-badge" id="synnioNotificationBadge">0</span>
                        </button>
                    </div>
                </header>

                <!-- Calendar Container -->
                <div class="synnio-calendar-container">
                    <div class="synnio-calendar-view" id="synnioCalendarView">
                        <!-- Kalender wird per JavaScript generiert -->
                    </div>
                </div>
            </main>

            <?php if ($show_right_panel) : ?>
            <!-- Right Panel -->
            <aside class="synnio-right-panel">
                <div class="synnio-right-panel-header">
                    <div class="synnio-right-panel-title"><?php _e('Kommende Termine', 'synnio-calendar'); ?></div>
                </div>
                <div class="synnio-right-panel-content">
                    <div id="synnioUpcomingEvents">
                        <!-- Kommende Termine werden per JS geladen -->
                    </div>

                    <div class="synnio-stats-grid">
                        <div class="synnio-stat-card">
                            <div class="synnio-stat-value" id="synnioStatAvailable"><?php echo esc_html($statistics['available_slots']); ?></div>
                            <div class="synnio-stat-label"><?php _e('Freie Slots diese Woche', 'synnio-calendar'); ?></div>
                        </div>
                        <div class="synnio-stat-card">
                            <div class="synnio-stat-value" id="synnioStatBooked"><?php echo esc_html($statistics['booked_events']); ?></div>
                            <div class="synnio-stat-label"><?php _e('Gebuchte Termine', 'synnio-calendar'); ?></div>
                        </div>
                        <div class="synnio-stat-card">
                            <div class="synnio-stat-value" id="synnioStatUtilization"><?php echo esc_html($statistics['utilization']); ?>%</div>
                            <div class="synnio-stat-label"><?php _e('Auslastung', 'synnio-calendar'); ?></div>
                        </div>
                        <div class="synnio-stat-card">
                            <div class="synnio-stat-value" id="synnioStatTodayAvailable"><?php echo esc_html($statistics['available_today']); ?></div>
                            <div class="synnio-stat-label"><?php _e('Heute noch frei', 'synnio-calendar'); ?></div>
                        </div>
                    </div>

                </div>
            </aside>
            <?php endif; ?>

            <!-- Modal: Neuer Termin -->
            <div class="synnio-modal-overlay" id="synnioEventModal">
                <div class="synnio-modal">
                    <div class="synnio-modal-header">
                        <h2 class="synnio-modal-title" id="synnioEventModalTitle"><?php _e('Neuer Termin', 'synnio-calendar'); ?></h2>
                        <button class="synnio-modal-close" data-close-modal="synnioEventModal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="synnio-modal-body">
                        <form id="synnioEventForm">
                            <input type="hidden" id="synnioEventId" name="event_id" value="">

                            <div class="synnio-form-group">
                                <label class="synnio-form-label"><?php _e('Titel', 'synnio-calendar'); ?></label>
                                <input type="text" class="synnio-form-input" id="synnioEventTitle" name="title" required placeholder="<?php _e('Termintitel eingeben...', 'synnio-calendar'); ?>">
                            </div>

                            <div class="synnio-form-row">
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Datum', 'synnio-calendar'); ?></label>
                                    <input type="date" class="synnio-form-input" id="synnioEventDate" name="date" required>
                                </div>
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Kalender', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioEventCalendar" name="calendar_id">
                                        <?php foreach ($calendars as $calendar) : ?>
                                        <option value="<?php echo esc_attr($calendar['id']); ?>" data-color="<?php echo esc_attr($calendar['color']); ?>"><?php echo esc_html($calendar['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="synnio-form-row">
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Startzeit', 'synnio-calendar'); ?></label>
                                    <input type="time" class="synnio-form-input" id="synnioEventStartTime" name="start_time" required>
                                </div>
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Endzeit', 'synnio-calendar'); ?></label>
                                    <input type="time" class="synnio-form-input" id="synnioEventEndTime" name="end_time" required>
                                </div>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-label"><?php _e('Termintyp', 'synnio-calendar'); ?></label>
                                <select class="synnio-form-select" id="synnioEventType" name="event_type">
                                    <?php foreach (Synnio_Calendar::get_event_types($user_id) as $key => $type) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($type['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-label"><?php _e('Beschreibung', 'synnio-calendar'); ?></label>
                                <textarea class="synnio-form-textarea" id="synnioEventDescription" name="description" placeholder="<?php _e('Optionale Beschreibung...', 'synnio-calendar'); ?>"></textarea>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-label"><?php _e('Farbe', 'synnio-calendar'); ?></label>
                                <div class="synnio-color-options" id="synnioColorOptions">
                                    <?php foreach (Synnio_Calendar::get_calendar_colors() as $color => $name) : ?>
                                    <div class="synnio-color-option" data-color="<?php echo esc_attr($color); ?>" style="background: <?php echo esc_attr($color); ?>;"></div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="synnioEventColor" name="color" value="#3B82F6">
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-checkbox">
                                    <input type="checkbox" id="synnioEventAllDay" name="all_day">
                                    <span><?php _e('Ganztaegig', 'synnio-calendar'); ?></span>
                                </label>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-checkbox">
                                    <input type="checkbox" id="synnioEventAvailable" name="is_available">
                                    <span><?php _e('Als "Verfuegbar fuer Buchung" markieren (API-sichtbar)', 'synnio-calendar'); ?></span>
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="synnio-modal-footer">
                        <button class="synnio-btn synnio-btn-danger" id="synnioDeleteEventBtn" style="display: none; margin-right: auto;">
                            <i class="fas fa-trash"></i>
                            <?php _e('Loeschen', 'synnio-calendar'); ?>
                        </button>
                        <button class="synnio-btn synnio-btn-secondary" data-close-modal="synnioEventModal"><?php _e('Abbrechen', 'synnio-calendar'); ?></button>
                        <button class="synnio-btn synnio-btn-primary" id="synnioSaveEventBtn">
                            <i class="fas fa-save"></i>
                            <?php _e('Speichern', 'synnio-calendar'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal: Sammelverarbeitung -->
            <div class="synnio-modal-overlay" id="synnioBatchModal">
                <div class="synnio-modal synnio-modal-large">
                    <div class="synnio-modal-header">
                        <h2 class="synnio-modal-title">
                            <i class="fas fa-layer-group"></i>
                            <?php _e('Sammelverarbeitung - Beratungsfenster', 'synnio-calendar'); ?>
                        </h2>
                        <button class="synnio-modal-close" data-close-modal="synnioBatchModal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="synnio-modal-body">
                        <form id="synnioBatchForm">
                            <div class="synnio-batch-section">
                                <div class="synnio-batch-title">
                                    <i class="fas fa-calendar-week"></i>
                                    <?php _e('Wochentage auswaehlen', 'synnio-calendar'); ?>
                                </div>
                                <div class="synnio-day-selector">
                                    <div class="synnio-day-chip" data-day="1"><?php _e('Mo', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="2"><?php _e('Di', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="3"><?php _e('Mi', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="4"><?php _e('Do', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="5"><?php _e('Fr', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="6"><?php _e('Sa', 'synnio-calendar'); ?></div>
                                    <div class="synnio-day-chip" data-day="0"><?php _e('So', 'synnio-calendar'); ?></div>
                                </div>
                            </div>

                            <div class="synnio-batch-section">
                                <div class="synnio-batch-title">
                                    <i class="fas fa-clock"></i>
                                    <?php _e('Uhrzeiten festlegen', 'synnio-calendar'); ?>
                                </div>
                                <div class="synnio-time-chips" id="synnioTimeChips">
                                    <div class="synnio-time-chip selected" data-time="10:00">10:00</div>
                                    <div class="synnio-time-chip selected" data-time="13:00">13:00</div>
                                    <div class="synnio-time-chip selected" data-time="15:00">15:00</div>
                                    <button type="button" class="synnio-add-time-btn" id="synnioAddTimeBtn">
                                        <i class="fas fa-plus"></i> <?php _e('Zeit hinzufuegen', 'synnio-calendar'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="synnio-form-row">
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Termintyp', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioBatchType" name="event_type">
                                        <?php
                                        $batch_types = Synnio_Calendar::get_event_types($user_id);
                                        $skip_batch = array('blocked', 'private', 'meeting');
                                        foreach ($batch_types as $key => $type) :
                                            if (in_array($key, $skip_batch)) continue;
                                        ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($type['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Dauer pro Slot', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioBatchDuration" name="duration">
                                        <option value="30">30 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                                        <option value="60" selected>60 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                                        <option value="90">90 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                                        <option value="120">120 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="synnio-form-row">
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Zeitraum von', 'synnio-calendar'); ?></label>
                                    <input type="date" class="synnio-form-input" id="synnioBatchStartDate" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Zeitraum bis', 'synnio-calendar'); ?></label>
                                    <input type="date" class="synnio-form-input" id="synnioBatchEndDate" name="end_date" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                                </div>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-checkbox">
                                    <input type="checkbox" id="synnioBatchAvailable" name="is_available" checked>
                                    <span><?php _e('Als "Verfuegbar" fuer KI-API markieren', 'synnio-calendar'); ?></span>
                                </label>
                            </div>

                            <div class="synnio-form-group">
                                <label class="synnio-form-checkbox">
                                    <input type="checkbox" id="synnioBatchExcludeHolidays" name="exclude_holidays">
                                    <span><?php _e('Feiertage automatisch ausschliessen', 'synnio-calendar'); ?></span>
                                </label>
                            </div>

                        </form>
                    </div>
                    <div class="synnio-modal-footer">
                        <button class="synnio-btn synnio-btn-secondary" data-close-modal="synnioBatchModal"><?php _e('Abbrechen', 'synnio-calendar'); ?></button>
                        <button class="synnio-btn synnio-btn-success" id="synnioBatchCreateBtn">
                            <i class="fas fa-magic"></i>
                            <?php _e('Termine erstellen', 'synnio-calendar'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal: Kalender bearbeiten -->
            <div class="synnio-modal-overlay" id="synnioEditCalendarModal">
                <div class="synnio-modal">
                    <div class="synnio-modal-header">
                        <h2 class="synnio-modal-title"><?php _e('Kalender bearbeiten', 'synnio-calendar'); ?></h2>
                        <button class="synnio-modal-close" data-close-modal="synnioEditCalendarModal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="synnio-modal-body">
                        <input type="hidden" id="synnioEditCalendarId" value="">
                        <div class="synnio-form-group">
                            <label class="synnio-form-label"><?php _e('Name', 'synnio-calendar'); ?></label>
                            <input type="text" class="synnio-form-input" id="synnioEditCalendarName" placeholder="<?php _e('Kalendername...', 'synnio-calendar'); ?>">
                        </div>
                        <div class="synnio-form-group">
                            <label class="synnio-form-label"><?php _e('Farbe', 'synnio-calendar'); ?></label>
                            <div class="synnio-color-options" id="synnioEditCalendarColors">
                                <?php foreach (Synnio_Calendar::get_calendar_colors() as $color => $name) : ?>
                                <div class="synnio-color-option" data-color="<?php echo esc_attr($color); ?>" style="background: <?php echo esc_attr($color); ?>;"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="synnioEditCalendarColor" value="">
                        </div>
                    </div>
                    <div class="synnio-modal-footer">
                        <button class="synnio-btn synnio-btn-danger" id="synnioDeleteCalendarBtn" style="margin-right: auto;">
                            <i class="fas fa-trash"></i>
                            <?php _e('Loeschen', 'synnio-calendar'); ?>
                        </button>
                        <button class="synnio-btn synnio-btn-secondary" data-close-modal="synnioEditCalendarModal"><?php _e('Abbrechen', 'synnio-calendar'); ?></button>
                        <button class="synnio-btn synnio-btn-primary" id="synnioSaveCalendarBtn">
                            <i class="fas fa-save"></i>
                            <?php _e('Speichern', 'synnio-calendar'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal: Einstellungen -->
            <div class="synnio-modal-overlay" id="synnioSettingsModal">
                <div class="synnio-modal synnio-modal-large">
                    <div class="synnio-modal-header">
                        <h2 class="synnio-modal-title"><?php _e('Kalender Einstellungen', 'synnio-calendar'); ?></h2>
                        <button class="synnio-modal-close" data-close-modal="synnioSettingsModal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="synnio-modal-body">
                        <div class="synnio-tabs">
                            <div class="synnio-tab active" data-tab="general"><?php _e('Allgemein', 'synnio-calendar'); ?></div>
                            <div class="synnio-tab" data-tab="eventTypes"><?php _e('Termintypen', 'synnio-calendar'); ?></div>
                            <div class="synnio-tab" data-tab="holidays"><?php _e('Feiertage', 'synnio-calendar'); ?></div>
                            <div class="synnio-tab" data-tab="sync"><?php _e('Synchronisation', 'synnio-calendar'); ?></div>
                            <div class="synnio-tab" data-tab="api"><?php _e('API', 'synnio-calendar'); ?></div>
                        </div>

                        <div class="synnio-tab-content active" id="synnioTabGeneral">
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Allgemeine Einstellungen', 'synnio-calendar'); ?></h3>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Standard-Ansicht', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioSettingsDefaultView">
                                        <option value="day"><?php _e('Tag', 'synnio-calendar'); ?></option>
                                        <option value="week"><?php _e('Woche', 'synnio-calendar'); ?></option>
                                        <option value="month"><?php _e('Monat', 'synnio-calendar'); ?></option>
                                        <option value="agenda"><?php _e('Agenda', 'synnio-calendar'); ?></option>
                                    </select>
                                </div>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Woche beginnt am', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioSettingsWeekStart">
                                        <option value="0"><?php _e('Sonntag', 'synnio-calendar'); ?></option>
                                        <option value="1"><?php _e('Montag', 'synnio-calendar'); ?></option>
                                    </select>
                                </div>

                                <?php
                                $display_start = get_user_meta($user_id, 'synnio_calendar_display_start', true);
                                $display_end = get_user_meta($user_id, 'synnio_calendar_display_end', true);
                                if (!$display_start) $display_start = '07:00';
                                if (!$display_end) $display_end = '20:00';
                                ?>
                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Kalender-Ansicht Zeitbereich', 'synnio-calendar'); ?></label>
                                    <p style="color: #6B7280; font-size: 12px; margin-bottom: 8px;">
                                        <?php _e('Der Kalender zeigt nur diesen Zeitbereich in der Wochen- und Tagesansicht an.', 'synnio-calendar'); ?>
                                    </p>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <input type="time" class="synnio-form-input" id="synnioSettingsDisplayStart" value="<?php echo esc_attr($display_start); ?>" style="width: 130px;">
                                        <span class="synnio-bh-separator"><?php _e('bis', 'synnio-calendar'); ?></span>
                                        <input type="time" class="synnio-form-input" id="synnioSettingsDisplayEnd" value="<?php echo esc_attr($display_end); ?>" style="width: 130px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Oeffnungszeiten -->
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Oeffnungszeiten', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Legen Sie Ihre Geschaeftszeiten fest. Ausserhalb dieser Zeiten werden keine Termine gebucht.', 'synnio-calendar'); ?>
                                </p>
                                <?php
                                $bh_raw = get_user_meta($user_id, 'synnio_calendar_business_hours', true);
                                $bh_data = $bh_raw ? json_decode($bh_raw, true) : null;
                                if (!$bh_data) {
                                    $bh_default_start = get_option('synnio_calendar_working_hours_start', '08:00');
                                    $bh_default_end = get_option('synnio_calendar_working_hours_end', '18:00');
                                    $bh_data = array();
                                    for ($d = 0; $d < 7; $d++) {
                                        $bh_data[$d] = array(
                                            'enabled' => ($d >= 1 && $d <= 5),
                                            'start' => $bh_default_start,
                                            'end' => $bh_default_end,
                                        );
                                    }
                                }
                                $bh_day_names = array(
                                    0 => __('Sonntag', 'synnio-calendar'),
                                    1 => __('Montag', 'synnio-calendar'),
                                    2 => __('Dienstag', 'synnio-calendar'),
                                    3 => __('Mittwoch', 'synnio-calendar'),
                                    4 => __('Donnerstag', 'synnio-calendar'),
                                    5 => __('Freitag', 'synnio-calendar'),
                                    6 => __('Samstag', 'synnio-calendar'),
                                );
                                // Reihenfolge: Mo-So
                                $bh_order = array(1, 2, 3, 4, 5, 6, 0);
                                ?>
                                <div id="synnioBusinessHours">
                                    <?php foreach ($bh_order as $d) :
                                        $day = isset($bh_data[$d]) ? $bh_data[$d] : array('enabled' => false, 'start' => '08:00', 'end' => '18:00');
                                    ?>
                                    <div class="synnio-business-hours-row" data-day="<?php echo $d; ?>">
                                        <label class="synnio-bh-day-label">
                                            <input type="checkbox" class="synnio-bh-enabled" data-day="<?php echo $d; ?>" <?php echo $day['enabled'] ? 'checked' : ''; ?>>
                                            <span><?php echo esc_html($bh_day_names[$d]); ?></span>
                                        </label>
                                        <input type="time" class="synnio-form-input synnio-bh-start" data-day="<?php echo $d; ?>" value="<?php echo esc_attr($day['start']); ?>">
                                        <span class="synnio-bh-separator"><?php _e('bis', 'synnio-calendar'); ?></span>
                                        <input type="time" class="synnio-form-input synnio-bh-end" data-day="<?php echo $d; ?>" value="<?php echo esc_attr($day['end']); ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Pausenzeiten -->
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Pausenzeiten', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Definieren Sie wiederkehrende Pausen. Waehrend Pausen werden keine Termine gebucht.', 'synnio-calendar'); ?>
                                </p>
                                <?php
                                $breaks_raw = get_user_meta($user_id, 'synnio_calendar_breaks', true);
                                $breaks_data = $breaks_raw ? json_decode($breaks_raw, true) : array();
                                ?>
                                <div id="synnioBreaksList">
                                    <?php foreach ($breaks_data as $idx => $brk) : ?>
                                    <div class="synnio-break-row" data-break-index="<?php echo $idx; ?>">
                                        <input type="text" class="synnio-form-input synnio-break-label" placeholder="<?php _e('z.B. Mittagspause', 'synnio-calendar'); ?>" value="<?php echo esc_attr($brk['label']); ?>">
                                        <input type="time" class="synnio-form-input synnio-break-start" value="<?php echo esc_attr($brk['start']); ?>">
                                        <span class="synnio-bh-separator"><?php _e('bis', 'synnio-calendar'); ?></span>
                                        <input type="time" class="synnio-form-input synnio-break-end" value="<?php echo esc_attr($brk['end']); ?>">
                                        <button type="button" class="synnio-btn synnio-btn-danger synnio-remove-break-btn" title="<?php _e('Entfernen', 'synnio-calendar'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="synnio-add-item-btn" id="synnioAddBreakBtn">
                                    <i class="fas fa-plus"></i> <?php _e('Pause hinzufuegen', 'synnio-calendar'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Tab: Termintypen -->
                        <div class="synnio-tab-content" id="synnioTabEventTypes">
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Standard-Termintypen', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Diese Termintypen sind fest vordefiniert.', 'synnio-calendar'); ?>
                                </p>
                                <?php foreach (Synnio_Calendar::get_event_types() as $key => $type) : ?>
                                <div class="synnio-event-type-display-row">
                                    <span class="synnio-event-type-color" style="background: <?php echo esc_attr($type['color']); ?>;"></span>
                                    <span class="synnio-event-type-label"><?php echo esc_html($type['label']); ?></span>
                                    <code class="synnio-event-type-key"><?php echo esc_html($key); ?></code>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Eigene Termintypen', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Erstellen Sie eigene Termintypen mit individuellem Namen und Farbe.', 'synnio-calendar'); ?>
                                </p>
                                <?php
                                $custom_types_raw = get_user_meta($user_id, 'synnio_calendar_custom_event_types', true);
                                $custom_types = $custom_types_raw ? json_decode($custom_types_raw, true) : array();
                                ?>
                                <div id="synnioCustomEventTypesList">
                                    <?php foreach ($custom_types as $idx => $ctype) : ?>
                                    <div class="synnio-custom-event-type-row" data-index="<?php echo $idx; ?>">
                                        <input type="text" class="synnio-form-input synnio-cet-key" placeholder="<?php _e('Schluessel (z.B. wartung)', 'synnio-calendar'); ?>" value="<?php echo esc_attr($ctype['key']); ?>">
                                        <input type="text" class="synnio-form-input synnio-cet-label" placeholder="<?php _e('Bezeichnung', 'synnio-calendar'); ?>" value="<?php echo esc_attr($ctype['label']); ?>">
                                        <input type="color" class="synnio-cet-color" value="<?php echo esc_attr($ctype['color']); ?>">
                                        <button type="button" class="synnio-btn synnio-btn-danger synnio-remove-cet-btn" title="<?php _e('Entfernen', 'synnio-calendar'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="synnio-add-item-btn" id="synnioAddEventTypeBtn">
                                    <i class="fas fa-plus"></i> <?php _e('Termintyp hinzufuegen', 'synnio-calendar'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Tab: Feiertage -->
                        <div class="synnio-tab-content" id="synnioTabHolidays">
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Gesetzliche Feiertage', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Laden Sie alle gesetzlichen Feiertage Ihres Bundeslandes herunter. Sie werden als ganztaegige blockierte Termine importiert, an denen keine Buchungen moeglich sind.', 'synnio-calendar'); ?>
                                </p>

                                <?php $saved_state = get_user_meta($user_id, 'synnio_calendar_holiday_state', true); ?>
                                <div class="synnio-form-row">
                                    <div class="synnio-form-group">
                                        <label class="synnio-form-label"><?php _e('Bundesland', 'synnio-calendar'); ?></label>
                                        <select class="synnio-form-select" id="synnioHolidayState">
                                            <option value=""><?php _e('-- Bitte waehlen --', 'synnio-calendar'); ?></option>
                                            <option value="BW" <?php selected($saved_state, 'BW'); ?>>Baden-Wuerttemberg</option>
                                            <option value="BY" <?php selected($saved_state, 'BY'); ?>>Bayern</option>
                                            <option value="BE" <?php selected($saved_state, 'BE'); ?>>Berlin</option>
                                            <option value="BB" <?php selected($saved_state, 'BB'); ?>>Brandenburg</option>
                                            <option value="HB" <?php selected($saved_state, 'HB'); ?>>Bremen</option>
                                            <option value="HH" <?php selected($saved_state, 'HH'); ?>>Hamburg</option>
                                            <option value="HE" <?php selected($saved_state, 'HE'); ?>>Hessen</option>
                                            <option value="MV" <?php selected($saved_state, 'MV'); ?>>Mecklenburg-Vorpommern</option>
                                            <option value="NI" <?php selected($saved_state, 'NI'); ?>>Niedersachsen</option>
                                            <option value="NW" <?php selected($saved_state, 'NW'); ?>>Nordrhein-Westfalen</option>
                                            <option value="RP" <?php selected($saved_state, 'RP'); ?>>Rheinland-Pfalz</option>
                                            <option value="SL" <?php selected($saved_state, 'SL'); ?>>Saarland</option>
                                            <option value="SN" <?php selected($saved_state, 'SN'); ?>>Sachsen</option>
                                            <option value="ST" <?php selected($saved_state, 'ST'); ?>>Sachsen-Anhalt</option>
                                            <option value="SH" <?php selected($saved_state, 'SH'); ?>>Schleswig-Holstein</option>
                                            <option value="TH" <?php selected($saved_state, 'TH'); ?>>Thueringen</option>
                                        </select>
                                    </div>
                                    <div class="synnio-form-group">
                                        <label class="synnio-form-label"><?php _e('Jahr', 'synnio-calendar'); ?></label>
                                        <select class="synnio-form-select" id="synnioHolidayYear">
                                            <?php for ($y = intval(date('Y')); $y <= intval(date('Y')) + 2; $y++) : ?>
                                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="button" class="synnio-btn synnio-btn-primary" id="synnioDownloadHolidaysBtn">
                                    <i class="fas fa-download"></i> <?php _e('Feiertage herunterladen und importieren', 'synnio-calendar'); ?>
                                </button>

                                <div id="synnioHolidayResult" style="margin-top: 16px; display: none;"></div>
                            </div>
                        </div>

                        <div class="synnio-tab-content" id="synnioTabSync">
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Kalender Synchronisation', 'synnio-calendar'); ?></h3>
                                <p style="color: #6B7280; font-size: 13px; margin-bottom: 16px;">
                                    <?php _e('Verbinden Sie Ihren Kalender mit Google Calendar oder Microsoft Outlook, um Termine automatisch zu synchronisieren.', 'synnio-calendar'); ?>
                                </p>

                                <div class="synnio-integration-card">
                                    <div class="synnio-integration-icon synnio-integration-google">
                                        <i class="fab fa-google"></i>
                                    </div>
                                    <div class="synnio-integration-info">
                                        <div class="synnio-integration-name">Google Calendar</div>
                                        <div class="synnio-integration-status" id="synnioGoogleStatus">
                                            <?php _e('Nicht verbunden', 'synnio-calendar'); ?>
                                        </div>
                                    </div>
                                    <div class="synnio-integration-actions">
                                        <button class="synnio-btn synnio-btn-primary" id="synnioConnectGoogleBtn">
                                            <i class="fab fa-google"></i>
                                            <?php _e('Verbinden', 'synnio-calendar'); ?>
                                        </button>
                                        <button class="synnio-btn synnio-btn-secondary synnio-sync-btn" id="synnioSyncGoogleBtn" style="display: none;" onclick="window.SynnioCalendar.syncEvents('google', 'both')">
                                            <i class="fas fa-sync"></i>
                                            <?php _e('Sync', 'synnio-calendar'); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="synnio-integration-card">
                                    <div class="synnio-integration-icon synnio-integration-outlook">
                                        <i class="fab fa-microsoft"></i>
                                    </div>
                                    <div class="synnio-integration-info">
                                        <div class="synnio-integration-name">Microsoft Outlook / Office 365</div>
                                        <div class="synnio-integration-status" id="synnioOutlookStatus">
                                            <?php _e('Nicht verbunden', 'synnio-calendar'); ?>
                                        </div>
                                    </div>
                                    <div class="synnio-integration-actions">
                                        <button class="synnio-btn synnio-btn-primary" id="synnioConnectOutlookBtn">
                                            <i class="fab fa-microsoft"></i>
                                            <?php _e('Verbinden', 'synnio-calendar'); ?>
                                        </button>
                                        <button class="synnio-btn synnio-btn-secondary synnio-sync-btn" id="synnioSyncOutlookBtn" style="display: none;" onclick="window.SynnioCalendar.syncEvents('outlook', 'both')">
                                            <i class="fas fa-sync"></i>
                                            <?php _e('Sync', 'synnio-calendar'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Sync-Einstellungen', 'synnio-calendar'); ?></h3>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Sync-Richtung', 'synnio-calendar'); ?></label>
                                    <select class="synnio-form-select" id="synnioSettingsSyncDirection" name="sync_direction">
                                        <option value="both"><?php _e('Bidirektional (Empfohlen)', 'synnio-calendar'); ?></option>
                                        <option value="import"><?php _e('Nur importieren', 'synnio-calendar'); ?></option>
                                        <option value="export"><?php _e('Nur exportieren', 'synnio-calendar'); ?></option>
                                    </select>
                                </div>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-checkbox">
                                        <input type="checkbox" id="synnioSyncBlocked" checked>
                                        <span><?php _e('Blockierte Zeiten importieren', 'synnio-calendar'); ?></span>
                                    </label>
                                </div>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-checkbox">
                                        <input type="checkbox" id="synnioSyncExport" checked>
                                        <span><?php _e('Gebuchte Termine exportieren', 'synnio-calendar'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="synnio-api-info">
                                <div class="synnio-api-info-title">
                                    <i class="fas fa-info-circle"></i>
                                    <?php _e('Hinweis zur Einrichtung', 'synnio-calendar'); ?>
                                </div>
                                <div style="font-size: 12px; color: #6B7280; line-height: 1.5;">
                                    <p><strong>Google Calendar:</strong> <?php _e('Erfordert Google Cloud Console Projekt mit aktivierter Calendar API und OAuth 2.0 Zugangsdaten.', 'synnio-calendar'); ?></p>
                                    <p style="margin-top: 8px;"><strong>Microsoft Outlook:</strong> <?php _e('Erfordert Azure AD App-Registrierung mit Microsoft Graph API Berechtigungen.', 'synnio-calendar'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="synnio-tab-content" id="synnioTabApi">
                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('Ihre Zugangsdaten', 'synnio-calendar'); ?></h3>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('Mandanten-ID (User-ID)', 'synnio-calendar'); ?></label>
                                    <div class="synnio-api-credential-row">
                                        <input type="text" class="synnio-form-input" value="<?php echo esc_attr($user_id); ?>" readonly id="synnioApiUserId">
                                        <button type="button" class="synnio-btn synnio-btn-secondary synnio-copy-btn" data-copy-target="synnioApiUserId" title="<?php _e('Kopieren', 'synnio-calendar'); ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="synnio-form-group">
                                    <label class="synnio-form-label"><?php _e('API-Schluessel', 'synnio-calendar'); ?></label>
                                    <div class="synnio-api-credential-row">
                                        <input type="text" class="synnio-form-input" value="<?php echo esc_attr(get_option('synnio_calendar_api_key', '')); ?>" readonly id="synnioApiKeyField">
                                        <button type="button" class="synnio-btn synnio-btn-secondary synnio-copy-btn" data-copy-target="synnioApiKeyField" title="<?php _e('Kopieren', 'synnio-calendar'); ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <p style="color: #6B7280; font-size: 12px; margin-top: 6px !important;"><?php _e('Verwenden Sie diesen Schluessel im Header X-Synnio-API-Key bei API-Anfragen.', 'synnio-calendar'); ?></p>
                                </div>
                            </div>

                            <div class="synnio-settings-section">
                                <h3 class="synnio-settings-title"><?php _e('API Endpunkte', 'synnio-calendar'); ?></h3>

                                <div class="synnio-api-info">
                                    <div class="synnio-api-info-title">
                                        <i class="fas fa-book"></i>
                                        <?php _e('Verfuegbare Endpunkte', 'synnio-calendar'); ?>
                                    </div>
                                    <div class="synnio-api-endpoint">
                                        GET /wp-json/synnio/v1/calendar/events
                                    </div>
                                    <div class="synnio-api-endpoint">
                                        GET /wp-json/synnio/v1/calendar/available-slots
                                    </div>
                                    <div class="synnio-api-endpoint">
                                        GET /wp-json/synnio/v1/calendar/check-availability
                                    </div>
                                    <div class="synnio-api-endpoint">
                                        GET /wp-json/synnio/v1/calendar/list-calendars
                                    </div>
                                    <div class="synnio-api-endpoint">
                                        POST /wp-json/synnio/v1/calendar/book-appointment
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="synnio-modal-footer">
                        <button class="synnio-btn synnio-btn-secondary" data-close-modal="synnioSettingsModal"><?php _e('Schliessen', 'synnio-calendar'); ?></button>
                        <button class="synnio-btn synnio-btn-primary" id="synnioSaveSettingsBtn">
                            <i class="fas fa-save"></i>
                            <?php _e('Speichern', 'synnio-calendar'); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
        </div><!-- /#synnio-calendar-wrapper -->
        <?php
        return ob_get_clean();
    }

    /**
     * Login erforderlich Meldung
     */
    private function render_login_required() {
        ob_start();
        ?>
        <div class="synnio-calendar-message synnio-calendar-login-required">
            <div class="synnio-message-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h3><?php _e('Anmeldung erforderlich', 'synnio-calendar'); ?></h3>
            <p><?php _e('Bitte melden Sie sich an, um auf den Kalender zuzugreifen.', 'synnio-calendar'); ?></p>
            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="synnio-btn synnio-btn-primary">
                <?php _e('Jetzt anmelden', 'synnio-calendar'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Modul nicht aktiviert Meldung
     */
    private function render_module_not_activated() {
        ob_start();
        ?>
        <div class="synnio-calendar-message synnio-calendar-not-activated">
            <div class="synnio-message-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h3><?php _e('Kalendermodul nicht freigeschalten', 'synnio-calendar'); ?></h3>
            <p><?php _e('Das Kalendermodul wurde fuer Ihr Konto noch nicht aktiviert. Bitte kontaktieren Sie Ihren Administrator.', 'synnio-calendar'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
