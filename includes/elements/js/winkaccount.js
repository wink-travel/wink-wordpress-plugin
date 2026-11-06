function initwinkAccount() {
    const winkRegisterBlockType = wp.blocks.registerBlockType; //Blocks API
    const winkCreateElement = wp.element.createElement; //React.createElement
    const wink__ = wp.i18n.__; //translation functions
    const winkInspectorControls = wp.blockEditor.InspectorControls; //Block inspector wrapper
    const winkTextControl = wp.components.TextControl; //WordPress form inputs and server-side renderer
    const winkServerSideRender = wp.serverSideRender; //WordPress form inputs and server-side renderer
    const blockName = 'winkaccount';
    const iconEl = winkCreateElement('img', { width: 24, src: winkData.imgURL + 'icon.svg' });
    winkRegisterBlockType( 'wink-blocks/'+blockName, {
        apiVersion: 3,
        title: wink__( 'WINK Account Button' ), // Block title.
        category:  winkData.blockCat, //category
        icon: iconEl,
        supports: {
            'multiple' : true,
            'align': [ 'left' ]
        },
        attributes:  {
            // "configurationId" : {
            //     default: '',
            // }
        },
        //display the post title
        edit(props){
            const winkBlockProps = wp.blockEditor.useBlockProps();
            const attributes =  props.attributes;
            const setAttributes =  props.setAttributes;
            if (winkData.mode == 'staging' || winkData.mode == 'development') {
                var preview = winkCreateElement( winkServerSideRender, {
                    block: 'wink-blocks/'+blockName,
                    attributes: attributes,
                    key: 'winkPreview_'+blockName
                } );
            } else {
                var preview = winkCreateElement(
                    'button',
                    {
                        class: blockName
                    },
                    winkCreateElement(
                        'img',
                        {
                            src: winkData.imgURL+'login.svg'
                        }
                    )
                );
            }

            const inspector = winkCreateElement( winkInspectorControls, {
                key: 'winkInspector_'+blockName 
            },
                [
                    winkCreateElement(
                        'p',
                        {},
                        wink__('Alternatively to this block, you can also use the following shortcode:')
                    ) 
                ],
                [
                    winkCreateElement(
                        'p',
                        {},
                        '['+blockName+']'
                    ) 
                ]
            );

            return winkCreateElement(
                'div',
                winkBlockProps,
                // Children of the main div as an array
                [ preview, inspector ]
            );
        },
        save(){
            return null;//save has to exist. This all we need
        }
    });
}

initwinkAccount();
