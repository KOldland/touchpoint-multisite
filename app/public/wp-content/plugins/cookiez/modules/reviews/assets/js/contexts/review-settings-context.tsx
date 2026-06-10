import {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
} from '@wordpress/element';

import ReviewPage from '../enums/review-page';

type ReviewSettingsState = {
	currentPage: ReviewPage;
	feedback: string;
	isOpened: boolean;
	rating: number;
	submitFailed: boolean;
	submitted: boolean;
};

export type ReviewSettingsContextValue = ReviewSettingsState & {
	setCurrentPage: (page: ReviewPage) => void;
	setFeedback: (feedback: string) => void;
	setIsOpened: (isOpened: boolean) => void;
	setRating: (rating: number) => void;
	setSubmitFailed: (failed: boolean) => void;
	setSubmitted: (submitted: boolean) => void;
};

const ReviewSettingsContext = createContext<
	ReviewSettingsContextValue | undefined
>(undefined);

type Props = {
	children: React.ReactNode;
};

const DEFAULT_STATE: ReviewSettingsState = {
	currentPage: ReviewPage.Ratings,
	feedback: '',
	isOpened: true,
	rating: 0,
	submitFailed: false,
	submitted: false,
};

export const ReviewSettingsProvider = ({ children }: Props) => {
	const [state, setState] = useState<ReviewSettingsState>(DEFAULT_STATE);

	const setRating = useCallback((rating: number) => {
		setState((prev) => ({ ...prev, rating }));
	}, []);

	const setFeedback = useCallback((feedback: string) => {
		setState((prev) => ({ ...prev, feedback }));
	}, []);

	const setCurrentPage = useCallback((currentPage: ReviewPage) => {
		setState((prev) => ({ ...prev, currentPage }));
	}, []);

	const setIsOpened = useCallback((isOpened: boolean) => {
		setState((prev) => ({ ...prev, isOpened }));
	}, []);

	const setSubmitFailed = useCallback((submitFailed: boolean) => {
		setState((prev) => ({ ...prev, submitFailed }));
	}, []);

	const setSubmitted = useCallback((submitted: boolean) => {
		setState((prev) => ({ ...prev, submitted }));
	}, []);

	const value = useMemo(
		() => ({
			...state,
			setCurrentPage,
			setFeedback,
			setIsOpened,
			setRating,
			setSubmitFailed,
			setSubmitted,
		}),
		[
			setCurrentPage,
			setFeedback,
			setIsOpened,
			setRating,
			setSubmitFailed,
			setSubmitted,
			state,
		],
	);

	return (
		<ReviewSettingsContext.Provider value={value}>
			{children}
		</ReviewSettingsContext.Provider>
	);
};

export const useReviewSettingsContext = (): ReviewSettingsContextValue => {
	const context = useContext(ReviewSettingsContext);
	if (context === undefined) {
		throw new Error(
			'useReviewSettingsContext must be used within a ReviewSettingsProvider',
		);
	}
	return context;
};
