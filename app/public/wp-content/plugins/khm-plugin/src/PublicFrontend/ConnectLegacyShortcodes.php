<?php

namespace KHM\PublicFrontend;

defined( 'ABSPATH' ) || exit;

class ConnectLegacyShortcodes {

	public function register(): void {
		add_shortcode( 'khm_connect_entry', array( $this, 'render_entry_form' ) );
		add_shortcode( 'khm_connect_shortlist', array( $this, 'render_shortlist' ) );
		add_shortcode( 'khm_connect_intro_form', array( $this, 'render_intro_form' ) );
		add_shortcode( 'khm_connect_thread_status', array( $this, 'render_thread_status' ) );
	}

	public function render_entry_form( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'shortlist_url' => home_url( '/connect-shortlist/' ),
				'show_name'     => 'yes',
				'show_email'    => 'yes',
			),
			$atts,
			'khm_connect_entry'
		);

		$container_id  = 'khm-connect-entry-' . wp_generate_uuid4();
		$shortlist_url = esc_url_raw( (string) $atts['shortlist_url'] );
		$show_name     = 'yes' === (string) $atts['show_name'];
		$show_email    = 'yes' === (string) $atts['show_email'];

		ob_start();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="khm-connect-entry">
			<style>
				#<?php echo esc_attr( $container_id ); ?> { max-width: 540px; font-family: inherit; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-field { margin-bottom: 14px; }
				#<?php echo esc_attr( $container_id ); ?> label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
				#<?php echo esc_attr( $container_id ); ?> input[type=text],
				#<?php echo esc_attr( $container_id ); ?> input[type=email],
				#<?php echo esc_attr( $container_id ); ?> select,
				#<?php echo esc_attr( $container_id ); ?> textarea { width: 100%; padding: 8px 10px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-consent { font-size: 12px; color: #555; display: flex; gap: 8px; align-items: flex-start; margin-bottom: 14px; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-consent input { margin-top: 2px; flex-shrink: 0; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-submit { background: #1a73e8; color: #fff; border: none; border-radius: 4px; padding: 10px 20px; font-size: 15px; cursor: pointer; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-submit:disabled { opacity: .5; cursor: default; }
				#<?php echo esc_attr( $container_id ); ?> .khm-entry-error { color: #c62828; font-size: 13px; margin-bottom: 10px; display: none; }
			</style>

			<form id="<?php echo esc_attr( $container_id ); ?>-form" novalidate>
				<?php if ( $show_name ) : ?>
				<div class="khm-entry-field">
					<label for="<?php echo esc_attr( $container_id ); ?>-name"><?php esc_html_e( 'Your name', 'khm-membership' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $container_id ); ?>-name" name="name" placeholder="<?php esc_attr_e( 'e.g. Jordan Clarke', 'khm-membership' ); ?>" autocomplete="name" />
				</div>
				<?php endif; ?>
				<?php if ( $show_email ) : ?>
				<div class="khm-entry-field">
					<label for="<?php echo esc_attr( $container_id ); ?>-email"><?php esc_html_e( 'Work email', 'khm-membership' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $container_id ); ?>-email" name="email" placeholder="<?php esc_attr_e( 'you@company.com', 'khm-membership' ); ?>" autocomplete="email" />
				</div>
				<?php endif; ?>
				<div class="khm-entry-field">
					<label for="<?php echo esc_attr( $container_id ); ?>-title"><?php esc_html_e( 'Your role', 'khm-membership' ); ?></label>
					<select id="<?php echo esc_attr( $container_id ); ?>-title" name="title_context">
						<option value=""><?php esc_html_e( '— Select your title —', 'khm-membership' ); ?></option>
						<option value="cmo"><?php esc_html_e( 'CMO / VP Marketing', 'khm-membership' ); ?></option>
						<option value="demand-gen"><?php esc_html_e( 'Demand Gen / Growth', 'khm-membership' ); ?></option>
						<option value="revops"><?php esc_html_e( 'Revenue / Sales Ops', 'khm-membership' ); ?></option>
						<option value="content"><?php esc_html_e( 'Content / Editorial', 'khm-membership' ); ?></option>
						<option value="product"><?php esc_html_e( 'Product / Solutions', 'khm-membership' ); ?></option>
						<option value="founder"><?php esc_html_e( 'Founder / CEO', 'khm-membership' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'khm-membership' ); ?></option>
					</select>
				</div>
				<div class="khm-entry-field">
					<label for="<?php echo esc_attr( $container_id ); ?>-challenge"><?php esc_html_e( 'Primary challenge right now', 'khm-membership' ); ?></label>
					<select id="<?php echo esc_attr( $container_id ); ?>-challenge" name="challenge">
						<option value=""><?php esc_html_e( '— Select a challenge —', 'khm-membership' ); ?></option>
						<option value="pipeline"><?php esc_html_e( 'Building pipeline / demand', 'khm-membership' ); ?></option>
						<option value="attribution"><?php esc_html_e( 'Attribution and reporting', 'khm-membership' ); ?></option>
						<option value="team"><?php esc_html_e( 'Growing or structuring team', 'khm-membership' ); ?></option>
						<option value="tech-stack"><?php esc_html_e( 'Evaluating or replacing tech stack', 'khm-membership' ); ?></option>
						<option value="content-ops"><?php esc_html_e( 'Content operations / editorial', 'khm-membership' ); ?></option>
						<option value="strategy"><?php esc_html_e( 'GTM strategy and positioning', 'khm-membership' ); ?></option>
					</select>
				</div>
				<div class="khm-entry-field">
					<label for="<?php echo esc_attr( $container_id ); ?>-context"><?php esc_html_e( 'Any additional context? (optional)', 'khm-membership' ); ?></label>
					<textarea id="<?php echo esc_attr( $container_id ); ?>-context" name="notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. We are a 50-person SaaS company scaling into enterprise...', 'khm-membership' ); ?>"></textarea>
				</div>
				<div class="khm-entry-consent">
					<input type="checkbox" id="<?php echo esc_attr( $container_id ); ?>-consent" name="consent" value="1" required />
					<label for="<?php echo esc_attr( $container_id ); ?>-consent">
						<?php
						printf(
							/* translators: 1: privacy policy link open tag 2: closing tag */
							esc_html__( 'I agree that my information may be used to match me with relevant providers and I understand I can withdraw consent at any time. See our %1$sPrivacy Policy%2$s.', 'khm-membership' ),
							'<a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '" target="_blank" rel="noopener">',
							'</a>'
						);
						?>
					</label>
				</div>
				<p class="khm-entry-error" aria-live="polite"></p>
				<button type="submit" class="khm-entry-submit"><?php esc_html_e( 'Find my matches', 'khm-membership' ); ?></button>
			</form>

			<script>
			(function() {
				var container   = document.getElementById(<?php echo wp_json_encode( $container_id ); ?>);
				if (!container) return;

				var form        = document.getElementById(<?php echo wp_json_encode( $container_id . '-form' ); ?>);
				var errEl       = form.querySelector('.khm-entry-error');
				var submitBtn   = form.querySelector('.khm-entry-submit');
				var shortlistUrl = <?php echo wp_json_encode( $shortlist_url ); ?>;

				// Persist UTM params from current URL into localStorage on load.
				(function captureUtm() {
					var params = new URLSearchParams(window.location.search);
					var utmKeys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','ref'];
					var stored = {};
					utmKeys.forEach(function(k) {
						var v = params.get(k);
						if (v) stored[k] = v;
					});
					if (Object.keys(stored).length) {
						try { localStorage.setItem('khm_connect_utm', JSON.stringify(stored)); } catch(e) {}
					}
				})();

				form.addEventListener('submit', function(e) {
					e.preventDefault();
					errEl.style.display = 'none';
					errEl.textContent = '';

					var titleCtx = form.querySelector('[name=title_context]') ? form.querySelector('[name=title_context]').value : '';
					var challenge = form.querySelector('[name=challenge]') ? form.querySelector('[name=challenge]').value : '';
					var consent   = form.querySelector('[name=consent]');

					if (!consent || !consent.checked) {
						errEl.textContent = <?php echo wp_json_encode( __( 'Please accept the consent statement to continue.', 'khm-membership' ) ); ?>;
						errEl.style.display = 'block';
						return;
					}
					if (!titleCtx) {
						errEl.textContent = <?php echo wp_json_encode( __( 'Please select your role to find relevant matches.', 'khm-membership' ) ); ?>;
						errEl.style.display = 'block';
						return;
					}

					// Store entry context for B2/B3 flow.
					var entry = {
						name:          (form.querySelector('[name=name]') || {value:''}).value.trim(),
						title_context: titleCtx,
						challenge:     challenge,
						notes:         (form.querySelector('[name=notes]') || {value:''}).value.trim(),
						consented_at:  new Date().toISOString(),
						captured_url:  window.location.href,
					};
					try { localStorage.setItem('khm_connect_entry', JSON.stringify(entry)); } catch(e) {}

					// Forward email separately — never stored in plain localStorage as name-linked PII.
					// (B2 shortlist endpoint does not require email — it uses title_context only.)

					submitBtn.disabled = true;
					var target = new URL(shortlistUrl, window.location.href);
					target.searchParams.set('title_context', titleCtx);
					if (challenge) target.searchParams.set('challenge', challenge);
					window.location.href = target.toString();
				});
			})();
			</script>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_shortlist( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'title_context' => '',
				'limit' => 8,
				'continue_url' => home_url( '/connect-intro/' ),
			),
			$atts,
			'khm_connect_shortlist'
		);

		$container_id = 'khm-connect-shortlist-' . wp_generate_uuid4();
		$rest_base    = trailingslashit( rest_url( 'khm/v1/connect' ) );
		$limit        = max( 1, min( 10, (int) $atts['limit'] ) );
		$title_ctx    = sanitize_text_field( (string) $atts['title_context'] );
		$continue_url = esc_url_raw( (string) $atts['continue_url'] );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="khm-connect-legacy">
			<h2>Connect Shortlist</h2>
			<p>Enter your requirements to shortlist sponsored providers.</p>
			<div class="khm-connect-grid">
				<label>Industries
					<input type="text" data-khm="industries" placeholder="e.g. fintech, ecommerce" />
				</label>
				<label>Regions
					<input type="text" data-khm="regions" placeholder="e.g. uk, eu, us" />
				</label>
				<label>Company Sizes
					<input type="text" data-khm="company_sizes" placeholder="e.g. smb, mid-market, enterprise" />
				</label>
				<label>Deployment
					<input type="text" data-khm="deployment" placeholder="e.g. saas, on-prem" />
				</label>
				<label>Keywords
					<input type="text" data-khm="keywords" placeholder="e.g. attribution, analytics" />
				</label>
				<label>Budget
					<input type="number" min="0" step="1" data-khm="budget" placeholder="e.g. 2500" />
				</label>
				<label>RFP Scope
					<select data-khm="rfp_scope">
						<option value="pilot_scheme">Structured pilot scheme (time-boxed, defined success criteria)</option>
						<option value="fsm_evaluation_poc">Complete FSM platform evaluation and POC</option>
						<option value="mobile_iot_optimisation">Mobile-first FSM with IoT optimisation</option>
						<option value="workforce_scheduling_upgrade">Workforce scheduling and dispatch modernisation</option>
					</select>
				</label>
				<label>Seat Band
					<select data-khm="rfp_seats">
						<option value="20_30">20-30 seats</option>
						<option value="50_100">50-100 seats</option>
						<option value="100_250">100-250 seats</option>
						<option value="500_plus">500+ seats</option>
					</select>
				</label>
				<label>Timeframe
					<select data-khm="rfp_timeframe">
						<option value="3_months">3 months</option>
						<option value="6_months">6 months</option>
						<option value="12_months">12 months</option>
					</select>
				</label>
				<label>Provisional Estimate (£)
					<input type="number" min="0" step="500" data-khm="rfp_estimate" value="120000" />
				</label>
				<div style="grid-column:1/-1;">
					<span style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Required Features</span>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;"><input type="checkbox" data-khm="rfp_feature" value="mobile_app" checked /> Mobile app</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;"><input type="checkbox" data-khm="rfp_feature" value="offline_capabilities" checked /> Offline capabilities</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;"><input type="checkbox" data-khm="rfp_feature" value="real_time_reporting" checked /> Real-time reporting dashboard</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;"><input type="checkbox" data-khm="rfp_feature" value="erp_integration" /> ERP integration</label>
				</div>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
				<button type="button" data-khm="run">Build shortlist</button>
				<button type="button" data-khm="compare" disabled>Compare selected</button>
			</div>
			<div data-khm="status" aria-live="polite"></div>
			<div data-khm="results"></div>
			<div data-khm="comparison" style="margin-top:16px;"></div>
		</div>
		<script>
		(function(){
			const root = document.getElementById(<?php echo wp_json_encode( $container_id ); ?>);
			if (!root) return;
			const restBase = <?php echo wp_json_encode( $rest_base ); ?>;
			const titleContext = <?php echo wp_json_encode( $title_ctx ); ?>;
			const limit = <?php echo wp_json_encode( $limit ); ?>;
			const continueUrl = <?php echo wp_json_encode( $continue_url ); ?>;
			const status = root.querySelector('[data-khm="status"]');
			const results = root.querySelector('[data-khm="results"]');
			const comparison = root.querySelector('[data-khm="comparison"]');
			const compareBtn = root.querySelector('[data-khm="compare"]');
			const toList = (value) => (value || '').split(',').map(v => v.trim()).filter(Boolean);
			const selectedIds = new Set();
			const getRfpSetup = () => {
				const features = Array.from(root.querySelectorAll('[data-khm="rfp_feature"]:checked')).map((el) => String(el.value || ''));
				return {
					scope: String((root.querySelector('[data-khm="rfp_scope"]') || {}).value || 'fsm_evaluation_poc'),
					seats: String((root.querySelector('[data-khm="rfp_seats"]') || {}).value || '20_30'),
					timeframe: String((root.querySelector('[data-khm="rfp_timeframe"]') || {}).value || '3_months'),
					provisional_estimate_gbp: Number((root.querySelector('[data-khm="rfp_estimate"]') || {}).value || 0),
					required_features: features
				};
			};

			const esc = (value) => String(value || '')
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#39;');

			const updateCompareState = () => {
				compareBtn.disabled = selectedIds.size < 2 || selectedIds.size > 5;
				if (selectedIds.size > 5) {
					status.textContent = 'Select between 2 and 5 providers to compare.';
				}
			};

			root.querySelector('[data-khm="run"]').addEventListener('click', async () => {
				status.textContent = 'Loading shortlist...';
				results.innerHTML = '';
				comparison.innerHTML = '';
				selectedIds.clear();
				updateCompareState();
				const rfpSetup = getRfpSetup();
				try { localStorage.setItem('khm_connect_rfp_setup', JSON.stringify(rfpSetup)); } catch (_) {}
				const payload = {
					title_context: titleContext,
					limit: limit,
					criteria: {
						industries: toList(root.querySelector('[data-khm="industries"]').value),
						regions: toList(root.querySelector('[data-khm="regions"]').value),
						company_sizes: toList(root.querySelector('[data-khm="company_sizes"]').value),
						deployment: toList(root.querySelector('[data-khm="deployment"]').value),
						keywords: toList(root.querySelector('[data-khm="keywords"]').value),
						budget: Number(root.querySelector('[data-khm="budget"]').value || 0),
						rfp_setup: rfpSetup
					}
				};
				try {
					const res = await fetch(restBase + 'shortlist', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						credentials: 'same-origin',
						body: JSON.stringify(payload)
					});
					const data = await res.json();
					if (!res.ok) {
						throw new Error(data && data.message ? data.message : 'Unable to load shortlist.');
					}
					const providers = Array.isArray(data.providers) ? data.providers : [];
					status.textContent = providers.length ? ('Found ' + providers.length + ' provider match(es).') : 'No providers matched yet.';
					results.innerHTML = providers.map((p) => {
						const reasons = Array.isArray(p.match_reasons) && p.match_reasons.length
							? '<ul>' + p.match_reasons.map(r => '<li>' + esc(r) + '</li>').join('') + '</ul>'
							: '<p>No match reasons available.</p>';
						const providerId = Number(p.id || p.provider_id || 0);
						const name = p.name || 'Provider';
						const currentRfpSetup = getRfpSetup();
						const introHref = continueUrl
							? (continueUrl + (continueUrl.includes('?') ? '&' : '?')
								+ 'provider_id=' + encodeURIComponent(String(p.id || p.provider_id || ''))
								+ '&provider_name=' + encodeURIComponent(name)
								+ '&rfp_scope=' + encodeURIComponent(String(currentRfpSetup.scope || ''))
								+ '&rfp_seats=' + encodeURIComponent(String(currentRfpSetup.seats || ''))
								+ '&rfp_timeframe=' + encodeURIComponent(String(currentRfpSetup.timeframe || ''))
								+ '&rfp_features=' + encodeURIComponent((currentRfpSetup.required_features || []).join(','))
								+ '&rfp_estimate=' + encodeURIComponent(String(currentRfpSetup.provisional_estimate_gbp || 0)))
							: '#';
						const checkbox = providerId > 0
							? '<label style="display:block;margin:6px 0;"><input type="checkbox" data-khm="select-provider" value="' + providerId + '" /> Compare</label>'
							: '';
						return '<article class="khm-connect-card">'
							+ '<h3>' + esc(name) + '</h3>'
							+ checkbox
							+ (p.description ? '<p>' + esc(p.description) + '</p>' : '')
							+ reasons
							+ '<p><a href="' + introHref + '">Continue to intro form</a></p>'
							+ '</article>';
					}).join('');

					results.querySelectorAll('[data-khm="select-provider"]').forEach((el) => {
						el.addEventListener('change', () => {
							const id = Number(el.value || 0);
							if (!id) return;
							if (el.checked) {
								selectedIds.add(id);
							} else {
								selectedIds.delete(id);
							}
							updateCompareState();
						});
					});
					updateCompareState();
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Unable to load shortlist.';
				}
			});

			compareBtn.addEventListener('click', async () => {
				const providerIds = Array.from(selectedIds.values());
				if (providerIds.length < 2 || providerIds.length > 5) {
					status.textContent = 'Select between 2 and 5 providers to compare.';
					return;
				}

				status.textContent = 'Building comparison...';
				comparison.innerHTML = '';

				try {
					const res = await fetch(restBase + 'compare', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						credentials: 'same-origin',
						body: JSON.stringify({
							title_context: titleContext,
							provider_ids: providerIds
						})
					});

					const data = await res.json();
					if (!res.ok) {
						throw new Error(data && data.message ? data.message : 'Unable to build comparison.');
					}

					const providerList = Array.isArray(data.providers) ? data.providers : [];
					const matrixRows = Array.isArray(data.matrix) ? data.matrix : [];

					const headerCells = providerList.map((p) => '<th>' + esc(p.name || 'Provider') + '</th>').join('');
					const rowHtml = matrixRows.map((row) => {
						const label = esc(row.label || row.key || 'Field');
						const values = Array.isArray(row.values) ? row.values : [];
						const cells = values.map((v) => '<td>' + esc(v) + '</td>').join('');
						return '<tr><th scope="row">' + label + '</th>' + cells + '</tr>';
					}).join('');

					comparison.innerHTML = '<h3>Provider Comparison</h3>'
						+ '<table class="widefat striped">'
						+ '<thead><tr><th>Field</th>' + headerCells + '</tr></thead>'
						+ '<tbody>' + rowHtml + '</tbody>'
						+ '</table>';

					status.textContent = 'Comparison ready.';
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Unable to build comparison.';
				}
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	public function render_intro_form( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'status_url' => home_url( '/connect-status/' ),
			),
			$atts,
			'khm_connect_intro_form'
		);

		$container_id = 'khm-connect-intro-' . wp_generate_uuid4();
		$rest_base    = trailingslashit( rest_url( 'khm/v1/connect' ) );
		$status_url   = esc_url_raw( (string) $atts['status_url'] );
		$provider_id  = isset( $_GET['provider_id'] ) ? (int) $_GET['provider_id'] : 0;
		$provider_name = isset( $_GET['provider_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['provider_name'] ) ) : '';

		ob_start();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="khm-connect-legacy">
			<h2>Connect Intro</h2>
			<p>Submit an intro request. Replies remain platform-mediated until handover is confirmed.</p>
			<label>Provider ID
				<input type="number" min="1" data-khm="provider_id" value="<?php echo esc_attr( (string) $provider_id ); ?>" required />
			</label>
			<?php if ( '' !== $provider_name ) : ?>
				<p><strong>Provider:</strong> <?php echo esc_html( $provider_name ); ?></p>
			<?php endif; ?>
			<label>Your Name
				<input type="text" data-khm="buyer_name" required />
			</label>
			<label>Your Email
				<input type="email" data-khm="buyer_email" required />
			</label>
			<label>Company (optional)
				<input type="text" data-khm="buyer_company" />
			</label>
			<fieldset style="border:1px solid #dcdcde;border-radius:6px;padding:12px;margin:10px 0;background:#fafbfc;">
				<legend style="font-size:12px;font-weight:600;padding:0 6px;">Mini-RFP Setup</legend>
				<label>Scope
					<select data-khm="rfp_scope">
						<option value="pilot_scheme">Structured pilot scheme (time-boxed, defined success criteria)</option>
						<option value="fsm_evaluation_poc">Complete FSM platform evaluation and POC</option>
						<option value="mobile_iot_optimisation">Mobile-first FSM with IoT optimisation</option>
						<option value="workforce_scheduling_upgrade">Workforce scheduling and dispatch modernisation</option>
					</select>
				</label>
				<label>Seats
					<select data-khm="rfp_seats">
						<option value="20_30">20-30 seats</option>
						<option value="50_100">50-100 seats</option>
						<option value="100_250">100-250 seats</option>
						<option value="500_plus">500+ seats</option>
					</select>
				</label>
				<label>Timeframe
					<select data-khm="rfp_timeframe">
						<option value="3_months">3 months</option>
						<option value="6_months">6 months</option>
						<option value="12_months">12 months</option>
					</select>
				</label>
				<label>Provisional Estimate (£)
					<input type="number" min="0" step="500" data-khm="rfp_estimate" value="120000" />
				</label>
				<div>
					<span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Required Features</span>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;"><input type="checkbox" data-khm="rfp_feature" value="mobile_app" checked /> Mobile app</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;"><input type="checkbox" data-khm="rfp_feature" value="offline_capabilities" checked /> Offline capabilities</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;"><input type="checkbox" data-khm="rfp_feature" value="real_time_reporting" checked /> Real-time reporting dashboard</label>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;"><input type="checkbox" data-khm="rfp_feature" value="erp_integration" /> ERP integration</label>
				</div>
			</fieldset>
			<label>Additional Message (optional)
				<textarea rows="5" data-khm="message">We would like a mediated intro and next-step discussion.</textarea>
			</label>
			<button type="button" data-khm="submit">Send intro request</button>
			<div data-khm="status" aria-live="polite"></div>
		</div>
		<script>
		(function(){
			const root = document.getElementById(<?php echo wp_json_encode( $container_id ); ?>);
			if (!root) return;
			const restBase = <?php echo wp_json_encode( $rest_base ); ?>;
			const statusUrl = <?php echo wp_json_encode( $status_url ); ?>;
			const status = root.querySelector('[data-khm="status"]');
			const params = new URLSearchParams(window.location.search || '');

			function applyBuyerRfpDefaults() {
				let stored = null;
				try { stored = JSON.parse(localStorage.getItem('khm_connect_rfp_setup') || 'null'); } catch (_) { stored = null; }
				const setup = stored || {};
				const scope = params.get('rfp_scope') || setup.scope || 'fsm_evaluation_poc';
				const seats = params.get('rfp_seats') || setup.seats || '20_30';
				const timeframe = params.get('rfp_timeframe') || setup.timeframe || '3_months';
				const estimate = Number(params.get('rfp_estimate') || setup.provisional_estimate_gbp || 120000);
				const featuresRaw = params.get('rfp_features') || (Array.isArray(setup.required_features) ? setup.required_features.join(',') : 'mobile_app,offline_capabilities,real_time_reporting');
				const features = String(featuresRaw || '').split(',').map((f) => f.trim()).filter(Boolean);

				const scopeEl = root.querySelector('[data-khm="rfp_scope"]');
				const seatsEl = root.querySelector('[data-khm="rfp_seats"]');
				const timeframeEl = root.querySelector('[data-khm="rfp_timeframe"]');
				const estimateEl = root.querySelector('[data-khm="rfp_estimate"]');
				if (scopeEl) scopeEl.value = scope;
				if (seatsEl) seatsEl.value = seats;
				if (timeframeEl) timeframeEl.value = timeframe;
				if (estimateEl) estimateEl.value = estimate > 0 ? String(estimate) : '120000';

				root.querySelectorAll('[data-khm="rfp_feature"]').forEach((el) => {
					el.checked = features.includes(String(el.value || ''));
				});
			}

			applyBuyerRfpDefaults();

			function collectBuyerRfpSetup() {
				const featureList = Array.from(root.querySelectorAll('[data-khm="rfp_feature"]:checked')).map((el) => String(el.value || ''));
				return {
					scope: String((root.querySelector('[data-khm="rfp_scope"]') || {}).value || 'fsm_evaluation_poc'),
					seats: String((root.querySelector('[data-khm="rfp_seats"]') || {}).value || '20_30'),
					timeframe: String((root.querySelector('[data-khm="rfp_timeframe"]') || {}).value || '3_months'),
					provisional_estimate_gbp: Number((root.querySelector('[data-khm="rfp_estimate"]') || {}).value || 0),
					required_features: featureList
				};
			}

			root.querySelector('[data-khm="submit"]').addEventListener('click', async () => {
				status.textContent = 'Submitting intro request...';
				const rfpSetup = collectBuyerRfpSetup();
				try { localStorage.setItem('khm_connect_rfp_setup', JSON.stringify(rfpSetup)); } catch (_) {}
				const rfpSummary =
					'Mini-RFP Setup\n'
					+ '- Scope: ' + rfpSetup.scope + '\n'
					+ '- Seats: ' + rfpSetup.seats + '\n'
					+ '- Timeframe: ' + rfpSetup.timeframe + '\n'
					+ '- Required Features: ' + (rfpSetup.required_features || []).join(', ') + '\n'
					+ '- Provisional Estimate (GBP): ' + String(rfpSetup.provisional_estimate_gbp || 0) + '\n\n';
				const extraMessage = (root.querySelector('[data-khm="message"]').value || '').trim();
				const payload = {
					provider_id: Number(root.querySelector('[data-khm="provider_id"]').value || 0),
					buyer_name: (root.querySelector('[data-khm="buyer_name"]').value || '').trim(),
					buyer_email: (root.querySelector('[data-khm="buyer_email"]').value || '').trim(),
					buyer_company: (root.querySelector('[data-khm="buyer_company"]').value || '').trim(),
					message: rfpSummary + (extraMessage || 'Buyer requests a mediated intro based on the mini-RFP criteria.'),
					session_id: 'connect-shortcode'
				};
				try {
					const res = await fetch(restBase + 'intro-threads', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						credentials: 'same-origin',
						body: JSON.stringify(payload)
					});
					const data = await res.json();
					if (!res.ok) {
						throw new Error(data && data.message ? data.message : 'Unable to create intro request.');
					}
					const threadId = Number(data.thread_id || data.id || 0);
					const token = String(data.buyer_token || '');
					if (!threadId || !token) {
						throw new Error('Intro request created but status link is unavailable.');
					}
					try {
						window.localStorage.setItem('khm_connect_last_thread_id', String(threadId));
						window.localStorage.setItem('khm_connect_last_buyer_token', token);
					} catch (_) {
						// Ignore storage failures in private browsing contexts.
					}
					status.textContent = 'Intro request sent. Redirecting to status...';
					const sep = statusUrl.includes('?') ? '&' : '?';
					window.location.href = statusUrl + sep + 'thread_id=' + encodeURIComponent(String(threadId)) + '&token=' + encodeURIComponent(token);
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Unable to submit intro request.';
				}
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	public function render_thread_status(): string {
		$container_id = 'khm-connect-status-' . wp_generate_uuid4();
		$rest_base    = trailingslashit( rest_url( 'khm/v1/connect' ) );
		$thread_id    = isset( $_GET['thread_id'] ) ? (int) $_GET['thread_id'] : 0;
		$token        = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) : '';

		ob_start();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="khm-connect-legacy">
			<h2>Connect Status</h2>
			<div data-khm="status" aria-live="polite">Loading thread status...</div>
			<div data-khm="content"></div>
		</div>
		<script>
		(function(){
			const root = document.getElementById(<?php echo wp_json_encode( $container_id ); ?>);
			if (!root) return;
			const restBase = <?php echo wp_json_encode( $rest_base ); ?>;
			let threadId = Number(<?php echo wp_json_encode( $thread_id ); ?> || 0);
			let token = String(<?php echo wp_json_encode( $token ); ?> || '');
			const status = root.querySelector('[data-khm="status"]');
			const content = root.querySelector('[data-khm="content"]');

			if (!threadId || !token) {
				try {
					threadId = threadId || Number(window.localStorage.getItem('khm_connect_last_thread_id') || 0);
					token = token || String(window.localStorage.getItem('khm_connect_last_buyer_token') || '');
				} catch (_) {
					// Ignore storage failures in private browsing contexts.
				}
			}

			const esc = (value) => String(value || '')
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#39;');

			const milestoneDate = (value) => {
				if (!value) return '—';
				const d = new Date(value);
				return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleString();
			};

			const humanizeEventKey = (value) => String(value || '')
				.replaceAll('_', ' ')
				.replace(/\b\w/g, (m) => m.toUpperCase());

			function renderTokenPrompt() {
				content.innerHTML = ''
					+ '<p>Missing thread details. Enter your thread ID and token to continue.</p>'
					+ '<p><label>Thread ID <input type="number" min="1" data-khm="manual-thread-id" /></label></p>'
					+ '<p><label>Token <input type="text" data-khm="manual-token" /></label></p>'
					+ '<p><button type="button" data-khm="manual-load">Load Status</button></p>';

				const loadButton = content.querySelector('[data-khm="manual-load"]');
				if (!loadButton) {
					return;
				}

				loadButton.addEventListener('click', () => {
					const manualThread = Number((content.querySelector('[data-khm="manual-thread-id"]') || {}).value || 0);
					const manualToken = String((content.querySelector('[data-khm="manual-token"]') || {}).value || '').trim();
					if (!manualThread || !manualToken) {
						status.textContent = 'Thread ID and token are required.';
						return;
					}

					threadId = manualThread;
					token = manualToken;
					try {
						window.localStorage.setItem('khm_connect_last_thread_id', String(threadId));
						window.localStorage.setItem('khm_connect_last_buyer_token', token);
					} catch (_) {
						// Ignore storage failures.
					}

					loadStatus();
				});
			}

			async function requestHandover() {
				status.textContent = 'Requesting handover...';
				try {
					const res = await fetch(restBase + 'intro-threads/' + encodeURIComponent(String(threadId)) + '/handover', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						credentials: 'same-origin',
						body: JSON.stringify({ token: token })
					});
					const data = await res.json();
					if (!res.ok) {
						throw new Error(data && data.message ? data.message : 'Unable to request handover.');
					}
					await loadStatus();
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Unable to request handover.';
				}
			}

			async function loadStatus() {
				if (!threadId || !token) {
					status.textContent = 'Missing thread_id or token.';
					renderTokenPrompt();
					return;
				}

				status.textContent = 'Loading thread status...';
				try {
					const res = await fetch(restBase + 'intro-threads/' + encodeURIComponent(String(threadId)) + '/status?token=' + encodeURIComponent(token), {
						credentials: 'same-origin'
					});
					const data = await res.json();
					if (!res.ok) {
						throw new Error(data && data.message ? data.message : 'Failed to load thread status.');
					}
					const handoverStatus = (data.handover_status || 'not_started');
					const canRequest = handoverStatus === 'not_started';
					const messages = Array.isArray(data.messages) ? data.messages : [];
					const sponsorMessages = messages.filter(m => String(m && m.sender_role || '') === 'sponsor');
					const latestSponsorReply = sponsorMessages.length ? sponsorMessages[sponsorMessages.length - 1] : null;
					const senderLabel = (role) => String(role || '') === 'buyer' ? 'You' : 'Provider';
					const milestones = Array.isArray(data.milestones) ? data.milestones : [];
					const timelineHtml = milestones.length
						? ('<ul>' + milestones.map((ev) => {
							const label = humanizeEventKey(ev.event_key || 'event');
							const at = milestoneDate(ev.event_at || '');
							return '<li><strong>' + esc(label) + ':</strong> ' + esc(at) + '</li>';
						}).join('') + '</ul>')
						: '<p>No timeline events yet.</p>';
					const handover = data.handover || null;
					const handoverRequestedAt = handover && (handover.buyer_requested_at || handover.requested_at || '');
					const handoverConfirmedAt = handover && (handover.sponsor_confirmed_at || handover.confirmed_at || '');
					status.textContent = 'Thread loaded.';
					try {
						window.localStorage.setItem('khm_connect_last_thread_id', String(threadId));
						window.localStorage.setItem('khm_connect_last_buyer_token', token);
					} catch (_) {
						// Ignore storage failures.
					}

					content.innerHTML = ''
						+ '<p><strong>Thread ID:</strong> ' + esc(data.thread_id || threadId) + '</p>'
						+ '<p><strong>Handover status:</strong> ' + esc(handoverStatus) + '</p>'
						+ '<ol>'
						+ '<li><strong>Request Sent:</strong> ' + milestoneDate(data.thread && data.thread.created_at) + '</li>'
						+ '<li><strong>Provider Replied:</strong> ' + milestoneDate(latestSponsorReply && latestSponsorReply.created_at) + '</li>'
						+ '<li><strong>Handover Requested:</strong> ' + milestoneDate(handoverRequestedAt) + '</li>'
						+ '<li><strong>Handover Confirmed:</strong> ' + milestoneDate(handoverConfirmedAt) + '</li>'
						+ '</ol>'
						+ (canRequest ? '<p><button type="button" data-khm="handover">Request direct handover</button></p>' : '')
						+ '<h3>Event Timeline</h3>'
						+ timelineHtml
						+ '<h3>Messages</h3>'
						+ (messages.length ? '<ul>' + messages.map(m => '<li><strong>' + esc(senderLabel(m.sender_role)) + ':</strong> ' + esc(m.message || '') + '</li>').join('') + '</ul>' : '<p>No messages yet.</p>');

					const button = content.querySelector('[data-khm="handover"]');
					if (button) {
						button.addEventListener('click', requestHandover);
					}
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Failed to load thread status.';
					content.innerHTML = '';
				}
			}

			loadStatus();
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}
}
