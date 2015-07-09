<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     * @return string
     */
    public function getNetworkNotice()
    {
        return $this->getNoticeHtml('Use your network administrator username to add this multisite network to your <a href="https://managewp.com" target="_blank">ManageWP</a> account.');
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

    public function getNotice() {
        return $this->getNoticeHtml('Add the site to your ManageWP dashboard to enable backups, uptime monitoring, website cleanup and a lot more!');
    }

    private function getNoticeHtml($message)
    {
        return <<<HTML
<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
    <style scoped type="text/css">
        .mwp-notice-container {
            box-shadow: 1px 1px 1px #d9d9d9;
            max-width: 100%;
            border-radius: 4px;
        }

        .mwp-notice {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            min-height: 116px;
            margin: 15px 0;
        }

        .mwp-notice-left {
            width: 113px;
            border-radius: 4px 0 0 4px;
            background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEYAAABGCAYAAABxLuKEAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RjE4NEQ5QkUwOTI3MTFFNUI5QTg4NEU0Q0Y1QUQzRkEiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RjE4NEQ5QkYwOTI3MTFFNUI5QTg4NEU0Q0Y1QUQzRkEiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpGMTg0RDlCQzA5MjcxMUU1QjlBODg0RTRDRjVBRDNGQSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpGMTg0RDlCRDA5MjcxMUU1QjlBODg0RTRDRjVBRDNGQSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PtHSG3YAAAdLSURBVHja7Jx7iBVV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) #1a84c6 no-repeat center center;
        }

        .mwp-notice-middle {
            padding: 21px 27px 19px;
            background: #fff;
        }

        .mwp-notice-middle h3 {
            font-size: 22px;
            line-height: 22px;
            font-weight: bold;
            margin: 0 0 10px;
            color: #333333;
        }

        .mwp-notice-middle p {
            margin: 10px 0 0;
            font-size: 16px;
            line-height: 21px;
            color: #666666;
        }

        .mwp-notice-right {
            background: #fff;
            width: 178px;
            padding: 26px;
            vertical-align: middle;
            border-radius: 0 4px 4px 0;
        }

        .mwp-notice-button {
            display: block;
            text-decoration: none !important;
            width: 178px;
            box-shadow: 0 -3px 0 0 #489b32 inset;
            color: #FFF !important;
            background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA0AAAAMCAYAAAC5tzfZAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NzEzQzFEMUIwOTE0MTFFNUE0RkVGNzVFNDE2QkQ0MDgiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NzEzQzFEMUMwOTE0MTFFNUE0RkVGNzVFNDE2QkQ0MDgiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo3MTNDMUQxOTA5MTQxMUU1QTRGRUY3NUU0MTZCRDQwOCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3MTNDMUQxQTA5MTQxMUU1QTRGRUY3NUU0MTZCRDQwOCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PjRM2JMAAACASURBVHjaYmTAAhbdniwIpGYCcXqcau57dHkmBuxACYp3Qw1AAYw4NMFs2w3luiLbiFMTPo1M+DRBFbkC8XtkpzICGeVAuoOBOHAWZAgLlFFBQLELFM8C2c5IyGigS9KQgn8WMQGBoYFQkGPVgFMTUIMLNKgxNBDyizEuOYAAAwBq9jbKOD6W0gAAAABJRU5ErkJggg==) #55b63b no-repeat 147px 19px;
            text-align: center;
            vertical-align: bottom;
            cursor: pointer;
            border: none;
            white-space: nowrap;
            padding: 0;
            border-radius: 4px;
            outline: 0 none !important;
            overflow: visible;
            font-size: 19px;
            line-height: 47px;
            margin: 0;
            box-sizing: border-box;
        }

    </style>

    <div class="mwp-notice-container">
        <table class="mwp-notice">
            <tr>
                <td class="mwp-notice-left">
                </td>
                <td class="mwp-notice-middle">
                    <h3>You&#8217;re almost there&hellip;</h3>

                    <p>{$message}</p>
                </td>
                <td class="mwp-notice-right">
                    <a class="mwp-notice-button" target="_blank" href="https://managewp.com/features">Learn more</a>
                </td>
            </tr>
        </table>
    </div>
</div>
HTML;
    }
}
