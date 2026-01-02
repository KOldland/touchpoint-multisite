console.log('JS loaded');

window.toggleExcerpt = function(button) {
	const textSpan = button.previousElementSibling;
	const fullText = textSpan.getAttribute('data-full');
	const isExpanded = button.classList.contains('expanded');

	if (!isExpanded) {
		textSpan.textContent = fullText;
		button.innerHTML = '<em><strong>Less</strong></em>';
		button.classList.add('expanded');
	} else {
		const wordLimit = 30;
		const words = fullText.split(' ');
		const shortText = words.slice(0, wordLimit).join(' ') + '…';
		textSpan.textContent = shortText;
		button.innerHTML = '<em><strong>More</strong></em>';
		button.classList.remove('expanded');
	}
};
