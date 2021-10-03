<?php
class gdrive
{
    public $name = 'Google Drive';
    private $id = '';
    private $title = '';
    private $image = '';
    private $referer = '';
    private $status = 'fail';
    private $url = 'https://docs.google.com/get_video_info?docid=';
    private $email = '';
    private $ch;

    function __construct($id = '', $email = '')
    {
        if (!empty($id)) {
            $id = explode('?', $id);
            $this->id = $id[0];
        }

        if (!empty($email)) {
            $this->email = $email;
        }

        session_write_close();
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_ENCODING, "");
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($this->ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        if (defined('CURLOPT_TCP_FASTOPEN')) {
            curl_setopt($this->ch, CURLOPT_TCP_FASTOPEN, 1);
        }
        curl_setopt($this->ch, CURLOPT_TCP_NODELAY, 1);
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($this->ch, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 30);
        // cek penggunaan proxy
        $proxy = proxy_rotator();
        if ($proxy) {
            curl_setopt($this->ch, CURLOPT_PROXY, $proxy['proxy']);
            curl_setopt($this->ch, CURLOPT_PROXYTYPE, $proxy['type']);
            curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $proxy['usrpwd']);
        }
    }

    private function get_api_sources($id = '', $email = '')
    {
        if (!empty($id)) {
            $auth = new \gdrive_auth();
            $auth->set_id($id);
            if (!empty($email)) {
                $auth->set_email($email);
                $token  = $auth->get_access_token();
            } else {
                $users  = $auth->get_accounts();
                if ($users) {
                    $key    = array_rand($users);
                    $user   = $users[$key];
                    $email  = $user['email'];
                    $auth->set_email($email);
                    $token  = $auth->get_access_token();
                } else {
                    $token = false;
                }
            }
            if ($token) {
                $info = $auth->get_file_info();
                $this->title = !empty($info['description']) && strpos($info['description'], 'copy by') === false && strpos($info['description'], 'uploaded') === false ? $info['description'] : $info['title'];
                $this->image = 'https://drive.google.com/thumbnail?id=' . $id . '&authuser=0&sz=w9999';
                $this->referer = 'https://youtube.googleapis.com/';

                curl_setopt($this->ch, CURLOPT_URL, $this->url . $id);
                curl_setopt($this->ch, CURLOPT_COOKIEJAR, BASE_DIR . 'cookies/gdrive~' . preg_replace('/[^A-Za-z0-9\-]/', '', $this->id) . '.txt');
                curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
                    'authorization: ' . $token['token_type'] . ' ' . $token['access_token']
                ));

                session_write_close();
                $data = curl_exec($this->ch);
                $err = curl_error($this->ch);

                if (!$err) {
                    if (strpos($data, 'recaptcha') !== FALSE) {
                        error_log('get_api_sources ' . $this->id . ' => g-recapcha detected');
                    } else {
                        parse_str($data, $json);
                        if (!empty($json['status']) && $json['status'] === 'ok') {
                            $excludes = ['5', '17', '36', '132', '133'];
                            $fmt_stream_map = $json['fmt_stream_map'];
                            $fmt_stream_map = explode(',', $fmt_stream_map);
                            if (!empty($fmt_stream_map)) {
                                $this->status = $json['status'];

                                $result = [];
                                foreach ($fmt_stream_map as $source) {
                                    $src = explode('|', $source);
                                    if (!in_array($src[0], $excludes)) {
                                        $result[] = [
                                            'file' => strtr(rawurldecode(end($src)), ['\u003d' => '=', '\u0026' => '&']),
                                            'type' => 'video/mp4',
                                            'label' => $this->label($src[0]),
                                        ];
                                    }
                                }
                                $result[] = [
                                    'file' => $result[0]['file'],
                                    'type' => $result[0]['type'],
                                    'label' => 'Default'
                                ];
                                if ($auth->download_checker()) {
                                    $result[] = [
                                        'file' => 'https://www.googleapis.com/drive/v3/files/' . $id . '?alt=media&source=downloadUrl',
                                        'type' => 'video/mp4',
                                        'label' => 'Original'
                                    ];
                                } else {
                                    $mirror = $auth->copy_files(true);
                                    if ($mirror) {
                                        $this->email = $mirror['owners'][0]['emailAddress'];
                                        $result[] = [
                                            'file' => 'https://www.googleapis.com/drive/v3/files/' . $mirror['id'] . '?alt=media&source=downloadUrl',
                                            'type' => 'video/mp4',
                                            'label' => 'Original'
                                        ];
                                    }
                                }
                                return $result;
                            } else {
                                error_log('get_api_sources ' . $id . ' => fmt_stream_map is empty');
                            }
                        } elseif (!empty($json['reason']) && strpos($json['reason'], 'processing') !== FALSE) {
                            $this->status = 'ok';

                            $result[] = [
                                'file' => 'https://www.googleapis.com/drive/v3/files/' . $id . '?alt=media&source=downloadUrl',
                                'type' => 'video/mp4',
                                'label' => 'Original'
                            ];
                            return $result;
                        } else {
                            if (!empty($json['reason'])) error_log('get_api_sources ' . $id . ' => ' . $json['reason']);
                            else error_log('get_api_sources ' . $id . ' => cannot get file info');
                        }
                    }
                } else {
                    error_log('get_api_sources ' . $id . ' => ' . $err);
                }
            }
        }
        return FALSE;
    }

    private function get_mirror()
    {
        if (!empty($this->id)) {
            $alwaysCopy = filter_var(get_option('gdrive_copy'), FILTER_VALIDATE_BOOLEAN);
            $copyToAll = filter_var(get_option('gdrive_copy_all'), FILTER_VALIDATE_BOOLEAN);
            if ($alwaysCopy) {
                $auth = new \gdrive_auth();
                $auth->set_id($this->id);
                if ($copyToAll) {
                    $mirrors = $auth->copy_files(false);
                    if ($mirrors) {
                        $key = array_rand($mirrors);
                        $mirror = $mirrors[$key];
                    }
                } else {
                    $mirror = $auth->copy_files(true);
                }
                if (!empty($mirror['id'])) {
                    return $this->get_api_sources($mirror['id'], $this->email);
                } else {
                    return $this->get_api_sources($this->id);
                }
            } else {
                $api = $this->get_api_sources($this->id, $this->email);
                if ($api) {
                    return $api;
                }
            }
        }
        return FALSE;
    }

    function get_sources()
    {
        $mirror = $this->get_mirror();
        if ($mirror) {
            return $mirror;
        } else {
            $auth = new \gdrive_auth();
            $auth->set_id($this->id);
            $dl = $auth->download_checker();
            if ($dl) {
                $email = $auth->get_email();
                if (empty($email)) {
                    $users = $auth->get_accounts();
                    if(!empty($users)){
                        $key = array_rand($users);
                        $user = $users[$key];
                        $email = $user['email'];
                    }
                } else {
                    $user = $auth->get_account();
                }
                if(!empty($user['api_key'])){
                    $this->status   = 'ok';
                    $this->title    = !empty($dl['description']) && strpos($dl['description'], 'copy by') === false && strpos($dl['description'], 'uploaded') === false ? $dl['description'] : $dl['title'];
                    $this->referer  = 'https://drive.google.com/file/d/' . $this->id . '/view';
                    $this->image    = 'https://drive.google.com/thumbnail?id=' . $this->id . '&authuser=0&sz=w9999';
                    $this->email    = $email;

                    $result[] = [
                        'file' => 'https://www.googleapis.com/drive/v3/files/' . $this->id . '?alt=media&source=downloadUrl&key=' . $user['api_key'],
                        'type' => 'video/mp4',
                        'label' => 'Original'
                    ];
                    return $result;
                }
            } else {
                $file = $auth->copy_files(true);
                if ($file) {
                    $this->status   = 'ok';
                    $this->title    = !empty($file['description']) && strpos($file['description'], 'copy by') === false && strpos($file['description'], 'uploaded') === false ? $file['description'] : $file['title'];
                    $this->referer  = 'https://drive.google.com/file/d/' . $file['id'] . '/view';
                    $this->image    = 'https://drive.google.com/thumbnail?id=' . $file['id'] . '&authuser=0&sz=w9999';
                    $this->email    = $file['owners'][0]['emailAddress'];
                    $auth->set_email($this->email);
                    $user = $auth->get_account();

                    $result[] = [
                        'file' => 'https://www.googleapis.com/drive/v3/files/' . $file['id'] . '?alt=media&source=downloadUrl&key=' . $user['api_key'],
                        'type' => 'video/mp4',
                        'label' => 'Original'
                    ];
                    return $result;
                }
            }
        }
        return [];
    }

    function get_email()
    {
        return $this->email;
    }

    function get_status()
    {
        return $this->status;
    }

    function get_title()
    {
        return $this->title;
    }

    function get_image()
    {
        return $this->image;
    }

    function get_referer()
    {
        return $this->referer;
    }

    function get_id()
    {
        return $this->id;
    }

    private function label($itag)
    {
        switch ($itag) {
            case '18':
                $label = "360p";
                break;
            case '59':
                $label = "480p";
                break;
            case '22':
                $label = "720p";
                break;
            case '37':
                $label = "1080p";
                break;
            case '34':
                $label = "360p";
                break;
            case '35':
                $label = "480p";
                break;
            case '38':
                $label = "Original";
                break;
            case '43':
                $label = "360p";
                break;
            case '44':
                $label = "480p";
                break;
            case '45':
                $label = "720p";
                break;
            case '46':
                $label = "1080p";
                break;
            case '82':
                $label = "360p";
                break;
            case '84':
                $label = "720p";
                break;
            case '102':
                $label = "360p";
                break;
            case '104':
                $label = "720p";
                break;
            default:
                $label = "Unknown";
                break;
        }
        return $label;
    }

    function __destruct()
    {
        curl_close($this->ch);
    }
}
