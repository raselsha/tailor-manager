<?php
defined('ABSPATH') || exit;

/**
 * WordPress core blocks .svg uploads by default because an SVG can embed <script>,
 * on*="" handlers, or <foreignObject> — a real stored-XSS risk if untrusted users could
 * upload one. This plugin only needs SVG for admin-picked Dress icons via wp.media, so
 * the mime type is enabled ONLY for users who already hold manage_options (the same
 * capability that gates every other write path in this plugin) — never for a lower role.
 * That capability gate is necessary but not sufficient on its own (an admin could still
 * upload a malicious file they got from someone else), so every .svg upload is also
 * content-scanned on the way in and rejected outright if it contains anything script-
 * capable, rather than attempting to surgically strip and re-serialize the markup.
 */
class TMR_Svg_Upload_Support
{
    /** @var string[] substrings that make an SVG reject outright, checked case-insensitively */
    private static $blocklist = array(
        '<script', 'javascript:', 'data:text/html', '<foreignobject',
        '<iframe', '<embed', '<object', 'onload=', 'onerror=', 'onclick=',
        'onmouseover=', 'onfocus=', 'onbegin=', '<!entity', '<!doctype',
    );

    public function __construct()
    {
        add_filter('upload_mimes', array($this, 'maybe_allow_svg_mime'));
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_filetype_detection'), 10, 4);
        add_filter('wp_handle_upload_prefilter', array($this, 'scan_svg_upload'));
    }

    public function maybe_allow_svg_mime($mimes)
    {
        if (current_user_can('manage_options')) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * finfo/getimagesize-based mime sniffing often misidentifies .svg as text/plain,
     * which makes wp_check_filetype_and_ext() reject it even after upload_mimes allows
     * the extension — this restores the extension-based type for .svg specifically,
     * the same fix WordPress core itself ships for a handful of other edge-case types.
     */
    public function fix_svg_filetype_detection($data, $file, $filename, $mimes)
    {
        if (!current_user_can('manage_options')) {
            return $data;
        }

        if (preg_match('/\.svg$/i', $filename)) {
            $data['ext']             = 'svg';
            $data['type']            = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }

        return $data;
    }

    /**
     * Blocklist-based content scan, run before the file is moved into place. Rejects
     * rather than attempts to strip-and-re-save — a full XML-aware sanitizer would be
     * the more thorough approach but needs a real parser library, which this plugin's
     * no-build-step convention doesn't have room for; a reject-on-suspicion blocklist is
     * the safe simple alternative (false positives just mean re-exporting a cleaner SVG,
     * never a security gap).
     */
    public function scan_svg_upload($file)
    {
        if (!isset($file['type']) || 'image/svg+xml' !== $file['type']) {
            return $file;
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return $file;
        }

        $contents = (string) file_get_contents($file['tmp_name'], false, null, 0, 200000);
        $lower    = strtolower($contents);

        foreach (self::$blocklist as $needle) {
            if (false !== strpos($lower, $needle)) {
                $file['error'] = __('এই SVG ফাইলে সন্দেহজনক কনটেন্ট (script/event handler) পাওয়া গেছে, তাই আপলোড প্রত্যাখ্যান করা হলো।', 'tailor-manager');
                return $file;
            }
        }

        return $file;
    }
}
