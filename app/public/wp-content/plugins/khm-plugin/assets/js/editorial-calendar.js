// Editorial Calendar (placeholder) React App
const { Card, CardBody, CardHeader } = wp.components;

const EditorialCalendarApp = () => {
    const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const blocks = Array.from({ length: 35 }, (_, index) => index);

    return wp.element.createElement(
        'div',
        { style: { padding: '20px' } },
        wp.element.createElement('h1', null, 'Editorial Calendar'),
        wp.element.createElement(
            'p',
            { style: { color: '#50575e', marginTop: '-4px' } },
            'Calendar planning view is coming soon.'
        ),
        wp.element.createElement(
            Card,
            { style: { marginTop: '16px' } },
            wp.element.createElement(CardHeader, null, 'Preview'),
            wp.element.createElement(
                CardBody,
                null,
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            border: '1px solid #dcdcde',
                            borderRadius: '8px',
                            padding: '16px',
                            background: '#fff',
                            filter: 'blur(2px)',
                            pointerEvents: 'none',
                            userSelect: 'none',
                        },
                    },
                    wp.element.createElement(
                        'div',
                        {
                            style: {
                                display: 'grid',
                                gridTemplateColumns: 'repeat(7, 1fr)',
                                gap: '8px',
                                marginBottom: '10px',
                                fontWeight: '600',
                                fontSize: '12px',
                                color: '#50575e',
                            },
                        },
                        ...weekDays.map((day) => wp.element.createElement('div', { key: day }, day))
                    ),
                    wp.element.createElement(
                        'div',
                        {
                            style: {
                                display: 'grid',
                                gridTemplateColumns: 'repeat(7, 1fr)',
                                gap: '8px',
                            },
                        },
                        ...blocks.map((block) =>
                            wp.element.createElement(
                                'div',
                                {
                                    key: block,
                                    style: {
                                        minHeight: '74px',
                                        border: '1px solid #dcdcde',
                                        borderRadius: '6px',
                                        background: block % 5 === 0 ? '#f0f6fc' : '#f9f9f9',
                                        padding: '6px',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        justifyContent: 'space-between',
                                    },
                                },
                                wp.element.createElement(
                                    'span',
                                    { style: { fontSize: '11px', color: '#50575e' } },
                                    String((block % 30) + 1)
                                ),
                                block % 4 === 0
                                    ? wp.element.createElement(
                                          'span',
                                          {
                                              style: {
                                                  fontSize: '10px',
                                                  background: '#dceeff',
                                                  borderRadius: '10px',
                                                  padding: '2px 6px',
                                                  alignSelf: 'flex-start',
                                              },
                                          },
                                          'Draft'
                                      )
                                    : null
                            )
                        )
                    )
                )
            )
        )
    );
};

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-calendar-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialCalendarApp), container);
    }
});
