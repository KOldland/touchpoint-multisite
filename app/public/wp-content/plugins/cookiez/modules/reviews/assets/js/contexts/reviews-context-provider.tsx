import { NotificationsProvider } from '@cookiez/globals';

import { ReviewSettingsProvider } from './review-settings-context';

type Props = {
	children: React.ReactNode;
};

export const ReviewsContextProvider = ({ children }: Props) => (
	<NotificationsProvider>
		<ReviewSettingsProvider>{children}</ReviewSettingsProvider>
	</NotificationsProvider>
);
