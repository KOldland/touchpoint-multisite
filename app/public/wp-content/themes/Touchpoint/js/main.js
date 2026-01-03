function khmGetExcerptParts(textSpan) {
	const fullText = textSpan.getAttribute('data-full') || textSpan.textContent || '';
	const shortAttr = textSpan.getAttribute('data-short');
	if (shortAttr) {
		return { fullText, shortText: shortAttr };
	}

	const wordLimit = 30;
	const words = fullText.trim().split(/\s+/).filter(Boolean);
	const shortText = words.length > wordLimit
		? words.slice(0, wordLimit).join(' ') + '…'
		: fullText;

	return { fullText, shortText };
}

function khmToggleExcerpt(button) {
	const textSpan = button.previousElementSibling;
	if (!textSpan) {
		return;
	}

	const parts = khmGetExcerptParts(textSpan);
	const isExpanded = button.classList.contains('expanded');

	if (!isExpanded) {
		textSpan.textContent = parts.fullText;
		button.innerHTML = '<em><strong>Less</strong></em>';
		button.classList.add('expanded');
	} else {
		textSpan.textContent = parts.shortText;
		button.innerHTML = '<em><strong>More</strong></em>';
		button.classList.remove('expanded');
	}
}

window.toggleExcerpt = function(button) {
	if (!button) {
		return;
	}
	khmToggleExcerpt(button);
};

document.addEventListener('click', (event) => {
	const button = event.target.closest('.excerpt-toggle');
	if (!button) {
		return;
	}
	khmToggleExcerpt(button);
});
