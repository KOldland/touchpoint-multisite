import { BlockingMode, CookieCategory } from './cookie-types';

export type ScriptItem = {
	id: number;
	cookieId: number | null;
	name: string;
	value: string;
	description: string;
	category: CookieCategory;
	blockingMode: BlockingMode;
};
