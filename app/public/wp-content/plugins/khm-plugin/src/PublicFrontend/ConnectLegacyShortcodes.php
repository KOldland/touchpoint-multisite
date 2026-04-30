<?php

namespace KHM\PublicFrontend;

defined( 'ABSPATH' ) || exit;

class ConnectLegacyShortcodes {

	public function register(): void {
		add_shortcode( 'khm_connect_shortlist', array( $this, 'render_shortlist' ) );
		add_shortcode( 'khm_connect_intro_form', array( $this, 'render_intro_form' ) );
		add_shortcode( 'khm_connect_thread_status', array( $this, 'render_thread_status' ) );
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
			</div>
			<button type="button" data-khm="run">Build shortlist</button>
			<div data-khm="status" aria-live="polite"></div>
			<div data-khm="results"></div>
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
			const toList = (value) => (value || '').split(',').map(v => v.trim()).filter(Boolean);

			root.querySelector('[data-khm="run"]').addEventListener('click', async () => {
				status.textContent = 'Loading shortlist...';
				results.innerHTML = '';
				const payload = {
					title_context: titleContext,
					limit: limit,
					criteria: {
						industries: toList(root.querySelector('[data-khm="industries"]').value),
						regions: toList(root.querySelector('[data-khm="regions"]').value),
						company_sizes: toList(root.querySelector('[data-khm="company_sizes"]').value),
						deployment: toList(root.querySelector('[data-khm="deployment"]').value),
						keywords: toList(root.querySelector('[data-khm="keywords"]').value),
						budget: Number(root.querySelector('[data-khm="budget"]').value || 0)
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
							? '<ul>' + p.match_reasons.map(r => '<li>' + String(r) + '</li>').join('') + '</ul>'
							: '<p>No match reasons available.</p>';
						const name = p.name || 'Provider';
						const introHref = continueUrl
							? (continueUrl + (continueUrl.includes('?') ? '&' : '?') + 'provider_id=' + encodeURIComponent(String(p.id || p.provider_id || '')) + '&provider_name=' + encodeURIComponent(name))
							: '#';
						return '<article class="khm-connect-card">'
							+ '<h3>' + name + '</h3>'
							+ (p.description ? '<p>' + p.description + '</p>' : '')
							+ reasons
							+ '<p><a href="' + introHref + '">Continue to intro form</a></p>'
							+ '</article>';
					}).join('');
				} catch (err) {
					status.textContent = err && err.message ? err.message : 'Unable to load shortlist.';
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
			<label>Message
				<textarea rows="5" data-khm="message" required>We would like a mediated intro and next-step discussion.</textarea>
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

			root.querySelector('[data-khm="submit"]').addEventListener('click', async () => {
				status.textContent = 'Submitting intro request...';
				const payload = {
					provider_id: Number(root.querySelector('[data-khm="provider_id"]').value || 0),
					buyer_name: (root.querySelector('[data-khm="buyer_name"]').value || '').trim(),
					buyer_email: (root.querySelector('[data-khm="buyer_email"]').value || '').trim(),
					buyer_company: (root.querySelector('[data-khm="buyer_company"]').value || '').trim(),
					message: (root.querySelector('[data-khm="message"]').value || '').trim(),
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
			const threadId = <?php echo wp_json_encode( $thread_id ); ?>;
			const token = <?php echo wp_json_encode( $token ); ?>;
			const status = root.querySelector('[data-khm="status"]');
			const content = root.querySelector('[data-khm="content"]');

			if (!threadId || !token) {
				status.textContent = 'Missing thread_id or token in URL.';
				return;
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
					const handover = data.handover || null;
					status.textContent = 'Thread loaded.';

					content.innerHTML = ''
						+ '<p><strong>Thread ID:</strong> ' + esc(data.thread_id || threadId) + '</p>'
						+ '<p><strong>Handover status:</strong> ' + esc(handoverStatus) + '</p>'
						+ '<ol>'
						+ '<li><strong>Request Sent:</strong> ' + milestoneDate(data.thread && data.thread.created_at) + '</li>'
						+ '<li><strong>Sponsor Replied:</strong> ' + (messages.length > 1 ? milestoneDate(messages[messages.length - 1].created_at) : '—') + '</li>'
						+ '<li><strong>Handover Requested:</strong> ' + milestoneDate(handover && handover.requested_at) + '</li>'
						+ '<li><strong>Handover Confirmed:</strong> ' + milestoneDate(handover && handover.confirmed_at) + '</li>'
						+ '</ol>'
						+ (canRequest ? '<p><button type="button" data-khm="handover">Request direct handover</button></p>' : '')
						+ '<h3>Messages</h3>'
						+ (messages.length ? '<ul>' + messages.map(m => '<li><strong>' + esc(m.sender_role || 'message') + ':</strong> ' + esc(m.message || '') + '</li>').join('') + '</ul>' : '<p>No messages yet.</p>');

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
