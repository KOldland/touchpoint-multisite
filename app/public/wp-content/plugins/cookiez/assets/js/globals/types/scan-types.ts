import { CookieCategory } from './cookie-types';

export enum ScanType {
	Homepage = 'home',
	Full = 'full',
	Custom = 'custom',
}

export enum ScanStatus {
	Completed = 'completed',
	InProgress = 'in_progress',
	Cancelled = 'cancelled',
	Failed = 'failed',
}

export enum ScanError {
	PageTooBig = 'page_too_big',
	DomainNotReachable = 'domain_not_reachable',
	AccessError = 'access_error',
	UnsupportedMediaType = 'unsupported_media_type',
	NotFound = 'not_found',
	QuotaExceeded = 'quota_exceeded',
	Generic = 'generic',
}

export type CookiesByCategory = Record<CookieCategory, number>;

export interface ScanUrl {
	url: string;
	status: ScanStatus;
	errorCode: string | null;
}

export interface ScanItem {
	id: number;
	createdAt: Date;
	status: ScanStatus;
	totalUrls: number;
	scannedUrls: number;
	categoriesFound: number;
	cookiesCount: number;
	scriptsCount: number;
	cookiesByCategory: CookiesByCategory;
	duration: number | null;
	urls: ScanUrl[];
}
