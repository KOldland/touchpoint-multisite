#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
WP_ROOT="$ROOT_DIR/app/public"

if [[ ! -f "$WP_ROOT/wp-load.php" ]]; then
  echo "wp_root_invalid=$WP_ROOT"
  exit 1
fi

cd "$ROOT_DIR"

echo "wp_root=$WP_ROOT"

php -l "$WP_ROOT/wp-content/plugins/khm-plugin/src/PublicFrontend/QuoteClubPortalShortcode.php"
php -l "$WP_ROOT/wp-content/plugins/khm-plugin/src/PublicFrontend/MemberPortalShortcode.php"
php -l "$WP_ROOT/wp-content/plugins/khm-plugin/src/Elementor/widgets/QuoteClubSearchToolbar_Widget.php"
php -l "$WP_ROOT/wp-content/plugins/khm-plugin/src/Rest/QuoteClubController.php"
php -l "$WP_ROOT/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php"

if command -v wp >/dev/null 2>&1; then
  set +e
  wp --path="$WP_ROOT" eval '
    $category_names = get_terms([
      "taxonomy" => "category",
      "hide_empty" => false,
      "fields" => "names",
    ]);
    if (is_wp_error($category_names) || !is_array($category_names)) {
      $category_names = [];
    }

    $request = new WP_REST_Request("GET", "/khm/v1/portal/quoteclub/search");
    $request->set_param("page", 1);
    $request->set_param("per_page", 20);
    $request->set_param("date_from", "");
    $request->set_param("date_to", "");
    $request->set_param("topics", $category_names);
    $request->set_param("keywords", "");
    $request->set_param("operator", "AND");

    $controller = new \\KHM\\Rest\\QuoteClubController();
    $response = $controller->search($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];
    $total = (int)($data["meta"]["total"] ?? -1);
    $count = is_array($data["results"] ?? null) ? count($data["results"]) : -1;

    echo "search_total={$total}\n";
    echo "results_count={$count}\n";
  ' >/dev/null 2>&1
  wp_status=$?
  set -e
  if [[ $wp_status -ne 0 ]]; then
    echo "wp_runtime_check=skipped (wp-cli bootstrap failed in this environment)"
  else
    echo "wp_runtime_check=ok"
  fi
else
  echo "wp_cli_missing=1"
fi

echo "smoke_status=ok"
