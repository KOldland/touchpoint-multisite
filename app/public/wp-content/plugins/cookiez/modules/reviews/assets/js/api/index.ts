import { APIBase } from '@cookiez/globals';

import {
	sendFeedbackPayloadSchema,
	type SendFeedbackPayload,
} from './review-schema';

const v1Prefix = '/cookiez/v1';

class APIReview extends APIBase {
	static async sendFeedback(data: SendFeedbackPayload): Promise<unknown> {
		const payload = sendFeedbackPayloadSchema.parse(data);

		return APIBase.request({
			method: 'POST',
			path: `${v1Prefix}/reviews/review`,
			data: payload,
		});
	}
}

export default APIReview;
