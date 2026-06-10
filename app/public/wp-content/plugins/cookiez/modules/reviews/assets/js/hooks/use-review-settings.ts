import {
	mixpanelEvents,
	mixpanelService,
	useRequest,
	useStorage,
	useToastNotification,
} from '@cookiez/globals';
import { date } from '@wordpress/date';
import { useCallback, useEffect, useMemo } from '@wordpress/element';
import { escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';

import APIReview from '../api';
import { reviewDataFromSiteSchema } from '../api/review-schema';
import {
	HIGH_RATING_THRESHOLD,
	HIDE_FOR_DAYS_INCREMENT,
	WORDPRESS_REVIEW_LINK,
} from '../constants';
import {
	type ReviewSettingsContextValue,
	useReviewSettingsContext,
} from '../contexts/review-settings-context';
import ReviewPage from '../enums/review-page';

import type { ReviewSiteStorageData } from '../types/review-site-storage-data';

type ReviewSettingsActions = {
	close: (event?: unknown, reason?: string) => void;
	dismiss: () => Promise<void>;
	isSubmitting: boolean;
	primarySubmit: () => Promise<void>;
	redirectToWpReview: () => Promise<void>;
	selectRating: (ratingValue: number) => Promise<void>;
};

export type ReviewSettings = ReviewSettingsContextValue & ReviewSettingsActions;

export function useReviewSettings(): ReviewSettings {
	const ctx = useReviewSettingsContext();
	const { save, get } = useStorage<ReviewSiteStorageData>();
	const { success, error } = useToastNotification();

	const { execute: sendFeedback, isLoading: isSubmitting } = useRequest(
		APIReview.sendFeedback,
	);

	const close = useCallback(
		(_event?: unknown, reason?: string) => {
			if ('backdropClick' !== reason) {
				ctx.setIsOpened(false);
			}

			mixpanelService.sendEvent(mixpanelEvents.reviewDismissClicked);
		},
		[ctx],
	);

	const hydrateFromWindow = useCallback(() => {
		const rawReviewData = window.cookiezReviewData?.reviewData;

		if (rawReviewData === undefined) {
			return;
		}

		if (
			rawReviewData === null ||
			typeof rawReviewData !== 'object' ||
			Array.isArray(rawReviewData)
		) {
			console.warn(
				'useReviewSettings: reviewData from site must be a plain object',
				rawReviewData,
			);
			return;
		}

		const validationResult = reviewDataFromSiteSchema.safeParse(rawReviewData);

		if (!validationResult.success) {
			console.warn(
				'useReviewSettings: validation error of review data from site',
				validationResult.error.issues,
			);
		}

		const reviewData = validationResult.success
			? validationResult.data
			: (rawReviewData as {
					rating?: number | string | null;
					repo_review_clicked?: boolean;
				});

		if (
			Number(reviewData.rating) >= HIGH_RATING_THRESHOLD &&
			!reviewData.repo_review_clicked
		) {
			ctx.setCurrentPage(ReviewPage.Review);
			ctx.setRating(Number(reviewData.rating));
		}
	}, [ctx]);

	useEffect(() => {
		hydrateFromWindow();
	}, [hydrateFromWindow]);

	const submit = useCallback(
		async (
			closeFn: () => void | Promise<void>,
			avoidClosing = false,
			submittedRating: number | null = null,
		): Promise<boolean> => {
			const ratingToSubmit = submittedRating ?? ctx.rating;
			try {
				ctx.setSubmitFailed(false);

				await sendFeedback({
					rating: ratingToSubmit,
					feedback: ctx.feedback,
				});

				const prevData = get.data?.cookiez_review_data ?? {};
				await save({
					cookiez_review_data: {
						...prevData,
						rating: ratingToSubmit,
						feedback: escapeHTML(ctx.feedback),
						submitted: true,
					},
				});

				if (ratingToSubmit && !ctx.feedback) {
					mixpanelService.sendEvent(mixpanelEvents.reviewStarSelected, {
						rating: ratingToSubmit,
					});
				}

				if (ctx.feedback) {
					mixpanelService.sendEvent(mixpanelEvents.reviewFeedbackSubmitted, {
						feedback_text: escapeHTML(ctx.feedback),
						rating: ratingToSubmit,
					});
				}

				if (ratingToSubmit < HIGH_RATING_THRESHOLD) {
					success(__('Thank you for your feedback!', 'cookiez'));
				}

				if (!avoidClosing) {
					await closeFn();
				}

				ctx.setSubmitted(true);

				return true;
			} catch {
				ctx.setSubmitted(false);
				ctx.setSubmitFailed(true);
				error(__('Failed to submit!', 'cookiez'));
				return false;
			}
		},
		[ctx, error, get, sendFeedback, save, success],
	);

	const dismiss = useCallback(async () => {
		if (get.hasFinishedResolution) {
			const prev = get.data?.cookiez_review_data ?? {
				dismissals: 0,
				hide_for_days: 0,
			};

			await save({
				cookiez_review_data: {
					...prev,
					dismissals: (prev.dismissals ?? 0) + 1,
					hide_for_days: (prev.hide_for_days ?? 0) + HIDE_FOR_DAYS_INCREMENT,
					last_dismiss: date('Y-m-d H:i:s'),
				},
			});
		}

		ctx.setIsOpened(false);
	}, [ctx, get, save]);

	const selectRating = useCallback(
		async (ratingValue: number) => {
			ctx.setSubmitFailed(false);
			ctx.setRating(ratingValue);

			if (ratingValue < HIGH_RATING_THRESHOLD) {
				ctx.setCurrentPage(ReviewPage.Feedback);
				return;
			}

			const didSucceed = await submit(close, true, ratingValue);
			if (didSucceed) {
				ctx.setCurrentPage(ReviewPage.Review);
			}
		},
		[close, ctx, submit],
	);

	const primarySubmit = useCallback(async () => {
		if (ctx.currentPage === ReviewPage.Ratings) {
			const didSucceed = await submit(close, true, ctx.rating);
			if (didSucceed) {
				ctx.setCurrentPage(ReviewPage.Review);
			}
			return;
		}

		await submit(close);
	}, [close, ctx, submit]);

	const redirectToWpReview = useCallback(async () => {
		mixpanelService.sendEvent(mixpanelEvents.reviewPublicRedirectClicked, {
			rating: ctx.rating,
			timestamp: new Date().toISOString(),
		});

		const prev = get.data?.cookiez_review_data ?? {};
		await save({
			cookiez_review_data: {
				...prev,
				repo_review_clicked: true,
			},
		});

		close();
		window.open(WORDPRESS_REVIEW_LINK, '_blank');
	}, [close, ctx.rating, get, save]);

	return useMemo(
		() => ({
			...ctx,
			close,
			dismiss,
			isSubmitting,
			primarySubmit,
			redirectToWpReview,
			selectRating,
		}),
		[
			close,
			ctx,
			dismiss,
			isSubmitting,
			primarySubmit,
			redirectToWpReview,
			selectRating,
		],
	);
}
