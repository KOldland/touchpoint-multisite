class NewsHeroCarouselHandler extends elementorModules.frontend.handlers.Base {
    getDefaultSettings() {
        return {
            selectors: {
                container: '.nhc-d8c6e538-container',
                prevBtn: '.nhc-d8c6e538-prev',
                nextBtn: '.nhc-d8c6e538-next'
            }
        };
    }

    getDefaultElements() {
        const selectors = this.getSettings('selectors');
        return {
            $container: this.$element.find(selectors.container),
            $prevBtn: this.$element.find(selectors.prevBtn),
            $nextBtn: this.$element.find(selectors.nextBtn)
        };
    }

    bindEvents() {
        this.elements.$nextBtn.on('click', () => this.scrollNext());
        this.elements.$prevBtn.on('click', () => this.scrollPrev());
    }

    scrollNext() {
        const container = this.elements.$container[0];
        if (container) {
            container.scrollBy({ left: window.innerWidth, behavior: 'smooth' });
        }
    }

    scrollPrev() {
        const container = this.elements.$container[0];
        if (container) {
            container.scrollBy({ left: -window.innerWidth, behavior: 'smooth' });
        }
    }
}

jQuery(window).on('elementor/frontend/init', () => {
    const addHandler = ($element) => {
        elementorFrontend.elementsHandler.addHandler(NewsHeroCarouselHandler, { $element });
    };
    elementorFrontend.hooks.addAction('frontend/element_ready/news_hero_carousel_d8c6e538.default', addHandler);
});
