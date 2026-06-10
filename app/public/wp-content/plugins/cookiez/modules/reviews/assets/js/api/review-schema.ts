import { z } from 'zod';

export const reviewDataFromSiteSchema = z
	.object({
		added_on: z.string().optional(),
		dismissals: z.number().optional(),
		feedback: z.string().nullable().optional(),
		hide_for_days: z.number().optional(),
		last_dismiss: z.string().nullable().optional(),
		rating: z.union([z.number(), z.string(), z.null()]).optional(),
		repo_review_clicked: z.boolean().optional(),
		submitted: z.boolean().optional(),
	})
	.passthrough();

export type ReviewDataFromSite = z.infer<typeof reviewDataFromSiteSchema>;

export const sendFeedbackPayloadSchema = z.object({
	rating: z.number().int().min(1).max(5),
	feedback: z.string(),
});

export type SendFeedbackPayload = z.infer<typeof sendFeedbackPayloadSchema>;
