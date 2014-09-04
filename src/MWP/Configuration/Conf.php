<?php
/**
 * Created by miljenko.rebernisak@prelovac.com
 * Date: 2/18/14
 */

/**
 * Class MWP_Configuration_Conf
 *
 * @package src\MWP\Configuration
 */
class MWP_Configuration_Conf
{

    /**
     * @var string
     */
    protected $notice;

    /**
     * @var string
     */
    protected $network_notice;
    /**
     * @var string
     */
    protected $deactivate_text;

    /**
     * @var string
     */
    protected $master_url;

    /**
     * @var string
     */
    protected $master_cron_url;

    /**
     * @var int
     */
    protected $noti_cache_life_time = 0;

    /**
     * @var int
     */
    protected $noti_treshold_spam_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_pending_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_approved_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_posts = 0;

    /**
     * @var int
     */
    protected $noti_treshold_drafts = 0;
    /**
     * @var string
     */
    protected $key_name;

    /**
     * @param array $data
     */
    public function __construct($data = array())
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Convert object to array. Much more convenient for wordpress serialization
     *
     * @return array
     */
    public function toArray()
    {
        $vars   = get_class_vars(get_class($this));
        $return = array();
        foreach ($vars as $key => $value) {
            $return[$key] = $this->$key;
        }

        return $return;
    }

    /**
     * We will use this function to notify server which fields and order to use in diff calculation
     * @return array
     */
    public function getVariables()
    {
        $vars = get_class_vars(get_class($this));

        return $vars;
    }

    /**
     * @param string $key_name
     */
    public function setKeyName($key_name)
    {
        $this->key_name = $key_name;
    }

    /**
     * @return string
     */
    public function getKeyName()
    {
        return $this->key_name;
    }

    /**
     * @param string $deactivate_text
     */
    public function setDeactivateText($deactivate_text)
    {
        $this->deactivate_text = $deactivate_text;
    }

    /**
     * @return string
     */
    public function getDeactivateText()
    {
        return $this->deactivate_text;
    }


    /**
     * @param string $network_notice
     */
    public function setNetworkNotice($network_notice)
    {
        $this->network_notice = $network_notice;
    }

    /**
     * @return string
     */
    public function getNetworkNotice()
    {
        return $this->network_notice;
    }


    /**
     * @param mixed $master_cron_url
     */
    public function setMasterCronUrl($master_cron_url)
    {
        $this->master_cron_url = $master_cron_url;
    }

    /**
     * @return mixed
     */
    public function getMasterCronUrl()
    {
        return $this->master_cron_url;
    }

    /**
     * @param mixed $master_url
     */
    public function setMasterUrl($master_url)
    {
        $this->master_url = $master_url;
    }

    /**
     * @return mixed
     */
    public function getMasterUrl()
    {
        return $this->master_url;
    }

    /**
     * @param mixed $noti_cache_life_time
     */
    public function setNotiCacheLifeTime($noti_cache_life_time)
    {
        $this->noti_cache_life_time = $noti_cache_life_time;
    }

    /**
     * @return mixed
     */
    public function getNotiCacheLifeTime()
    {
        return $this->noti_cache_life_time;
    }

    /**
     * @param mixed $noti_treshold_approved_comments
     */
    public function setNotiTresholdApprovedComments($noti_treshold_approved_comments)
    {
        $this->noti_treshold_approved_comments = $noti_treshold_approved_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdApprovedComments()
    {
        return $this->noti_treshold_approved_comments;
    }

    /**
     * @param mixed $noti_treshold_drafts
     */
    public function setNotiTresholdDrafts($noti_treshold_drafts)
    {
        $this->noti_treshold_drafts = $noti_treshold_drafts;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdDrafts()
    {
        return $this->noti_treshold_drafts;
    }

    /**
     * @param mixed $noti_treshold_pending_comments
     */
    public function setNotiTresholdPendingComments($noti_treshold_pending_comments)
    {
        $this->noti_treshold_pending_comments = $noti_treshold_pending_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdPendingComments()
    {
        return $this->noti_treshold_pending_comments;
    }

    /**
     * @param mixed $noti_treshold_posts
     */
    public function setNotiTresholdPosts($noti_treshold_posts)
    {
        $this->noti_treshold_posts = $noti_treshold_posts;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdPosts()
    {
        return $this->noti_treshold_posts;
    }

    /**
     * @param mixed $noti_treshold_spam_comments
     */
    public function setNotiTresholdSpamComments($noti_treshold_spam_comments)
    {
        $this->noti_treshold_spam_comments = $noti_treshold_spam_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdSpamComments()
    {
        return $this->noti_treshold_spam_comments;
    }

    /**
     * @param mixed $notice
     */
    public function setNotice($notice)
    {
        $this->notice = $notice;
    }

    /**
     * @return mixed
     */
    public function getNotice()
    {
        return $this->notice;
    }


}
