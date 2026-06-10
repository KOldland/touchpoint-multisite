import { Notifications } from '@cookiez/globals';

import UserFeedbackForm from './layouts/user-feedback-form';

const ReviewsApp = () => {
	return (
		<>
			<UserFeedbackForm />
			<Notifications />
		</>
	);
};

export default ReviewsApp;
