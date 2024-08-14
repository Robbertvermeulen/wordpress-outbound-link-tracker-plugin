<?php
namespace EPICWP\Outbound_Link_Tracking;

use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Os;

class Tracked_Link {

    protected $id;
    protected $link_redirects;
    protected $browser = [];
    protected $os = [];

    public function __construct(int $link_id) {
        $this->id = $link_id;
        $this->link_redirects = get_field('link_redirects', $this->id) ?? [];
        $this->set_system_info();
    }

    public function handle_visit() {
        $this->track();
        $this->redirect();
    }

    public function track() {

        // If both browser and os are not set, we don't track the visit
        if ($this->browser['name'] === 'unknown' && $this->os['name'] === 'unknown') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'tracked_link_redirects';
        $wpdb->insert($table_name, [
            'link_id' => $this->id,
            'date' => current_time('mysql'),
            'browser' => $this->browser['name'] ?? null,
            'os' => $this->os['name'] ?? null,
        ]);
    }

    public function redirect() {
        $redirect_url = $this->get_redirect_url();
        if ($redirect_url) {
            wp_redirect($redirect_url, 302);
        } else {
            wp_redirect(home_url(), 302);
        }
        exit;
    }

    public function get_redirect_url() {
        $redirect_url = null;
        if (!empty($this->link_redirects)) {

            $common_count = 0;

            foreach ($this->link_redirects as $link_redirect) {
                $count = 0;
                $url = $link_redirect['url'];
                $browser = $link_redirect['browser'];
                $os = $link_redirect['operating_system'];
                
                if (!empty($browser)) {
                    if (!empty($this->browser['name']) && $this->browser['name'] === $browser) {
                        $count ++;
                    } else {
                        continue;
                    }
                }

                if (!empty($os)) {
                    if (!empty($this->os['name']) && $this->os['name'] === $os) {
                        $count ++;
                    } else {
                        continue;
                    }
                }

                if ($count >= $common_count) {
                    $common_count = $count;
                    $redirect_url = $url;
                }
            }
        }
        return $redirect_url;
    }

    protected function set_system_info() {
        $browser = new Browser();
        $os = new Os();

        if (!empty($browser->getName())) {
            $this->browser['name'] = str_replace(' ', '_', strtolower($browser->getName()));
        }

        if (!empty($os->getName())) {
            $this->os['name'] = str_replace(' ', '_', strtolower($os->getName()));
        }
    }

}