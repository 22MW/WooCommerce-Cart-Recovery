<?php
defined('ABSPATH') || exit;

/**
 * GitHub Releases updater for WooCommerce Cart Recovery.
 */
final class WCCR_Github_Updater
{
	private const REPO       = '22MW/WooCommerce-Cart-Recovery';
	private const ASSET_NAME = 'woocommerce-cart-recovery.zip';
	private const SLUG       = 'woocommerce-cart-recovery';
	private const CACHE_KEY  = 'wccr_github_release_latest';

	public function register_hooks(): void
	{
		add_filter('site_transient_update_plugins', array($this, 'filter_plugin_updates'));
		add_filter('plugins_api', array($this, 'filter_plugin_info'), 10, 3);
		add_filter('upgrader_source_selection', array($this, 'fix_source_dir'), 10, 4);
	}

	/**
	 * Fetch the latest release from GitHub (cached 1 hour).
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_latest_release(): ?array
	{
		$cached = get_transient(self::CACHE_KEY);
		if (is_array($cached)) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WooCommerce-Cart-Recovery',
				),
			)
		);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (! is_array($data)) {
			return null;
		}

		set_transient(self::CACHE_KEY, $data, HOUR_IN_SECONDS);
		return $data;
	}

	/**
	 * Extract version string from a release (strips leading 'v').
	 *
	 * @param array<string,mixed> $release
	 */
	private function get_remote_version(array $release): string
	{
		return ltrim((string) ($release['tag_name'] ?? ''), 'v');
	}

	/**
	 * Return the download URL for the release ZIP.
	 * Prefers the named asset, falls back to the tag ZIP.
	 *
	 * @param array<string,mixed> $release
	 */
	private function get_package_url(array $release): string
	{
		if (! empty($release['assets']) && is_array($release['assets'])) {
			foreach ($release['assets'] as $asset) {
				if (is_array($asset) && isset($asset['name']) && $asset['name'] === self::ASSET_NAME) {
					return (string) ($asset['browser_download_url'] ?? '');
				}
			}
		}

		$tag = (string) ($release['tag_name'] ?? '');
		if ($tag === '') {
			return '';
		}

		return 'https://github.com/' . self::REPO . '/archive/refs/tags/' . $tag . '.zip';
	}

	/**
	 * Inject update info into the WP update transient.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function filter_plugin_updates(object $transient): object
	{
		if (! isset($transient->checked) || ! is_array($transient->checked)) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if (! $release) {
			return $transient;
		}

		$remote_version = $this->get_remote_version($release);
		$plugin_slug    = self::SLUG . '/' . self::SLUG . '.php';

		if (empty($remote_version) || empty($transient->checked[$plugin_slug])) {
			return $transient;
		}

		if (version_compare($remote_version, $transient->checked[$plugin_slug], '<=')) {
			return $transient;
		}

		$transient->response[$plugin_slug] = (object) array(
			'slug'        => self::SLUG,
			'plugin'      => $plugin_slug,
			'new_version' => $remote_version,
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $this->get_package_url($release),
		);

		return $transient;
	}

	/**
	 * Provide plugin info for the WP update details screen.
	 *
	 * @param false|object|array   $result
	 * @param string               $action
	 * @param object               $args
	 * @return false|object
	 */
	public function filter_plugin_info($result, string $action, object $args)
	{
		if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== self::SLUG) {
			return $result;
		}

		$release = $this->get_latest_release();
		if (! $release) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'WooCommerce Cart Recovery';
		$info->slug          = self::SLUG;
		$info->version       = $this->get_remote_version($release);
		$info->author        = '22MW';
		$info->homepage      = 'https://github.com/' . self::REPO;
		$info->download_link = $this->get_package_url($release);
		$info->sections      = array(
			'description' => 'Recover abandoned WooCommerce carts with scheduled emails, native coupons and locale-aware templates.',
		);

		return $info;
	}

	/**
	 * Rename the extracted folder to the expected plugin slug if needed.
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param object $upgrader
	 * @param array<string,mixed> $hook_extra
	 * @return string
	 */
	public function fix_source_dir(string $source, string $remote_source, object $upgrader, array $hook_extra): string
	{
		$plugin_slug = self::SLUG . '/' . self::SLUG . '.php';

		if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $plugin_slug) {
			return $source;
		}

		if (basename($source) === self::SLUG) {
			return $source;
		}

		$corrected = trailingslashit(dirname($source)) . self::SLUG;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if (@rename($source, $corrected)) {
			return $corrected;
		}

		return $source;
	}
}
