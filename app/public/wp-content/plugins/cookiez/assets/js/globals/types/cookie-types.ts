export enum CookieCategory {
	Necessary = 'necessary',
	Functional = 'functional',
	Analytics = 'analytics',
	Advertising = 'advertising',
	Unclassified = 'unclassified',
}

export enum BlockingMode {
	AlwaysBlock = 'always',
	BlockUntilConsent = 'until_consent',
	NeverBlock = 'never',
}

export interface CookieItem {
	id: number;
	name: string;
	duration: number | null;
	category: CookieCategory;
	description: string;
	domain: string;
}
