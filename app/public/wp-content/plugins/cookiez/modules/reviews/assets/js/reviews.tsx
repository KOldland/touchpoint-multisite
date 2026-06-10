import DirectionProvider from '@elementor/ui/DirectionProvider';
import { ThemeProvider } from '@elementor/ui/styles';
import domReady from '@wordpress/dom-ready';
import { Fragment, StrictMode, createRoot } from '@wordpress/element';

import ReviewsApp from './app';
import { ReviewsContextProvider } from './contexts/reviews-context-provider';

domReady(() => {
	const rootNode = document.getElementById('reviews-app');
	if (!rootNode) {
		return;
	}

	const isDevelopment =
		window.cookiezReviewData?.isDevelopment ??
		window.cookiezSettingsData?.isDevelopment;
	const isRTL = window.cookiezReviewData?.isRTL;
	const AppWrapper = Boolean(isDevelopment) ? StrictMode : Fragment;

	const root = createRoot(rootNode);

	root.render(
		<AppWrapper>
			<DirectionProvider rtl={isRTL}>
				<ThemeProvider colorScheme="light">
					<ReviewsContextProvider>
						<ReviewsApp />
					</ReviewsContextProvider>
				</ThemeProvider>
			</DirectionProvider>
		</AppWrapper>,
	);
});
