function initwinkContent() {
    const winkRegisterBlockType = wp.blocks.registerBlockType; //Blocks API
    const winkCreateElement = wp.element.createElement; //React.createElement
    const wink__ = wp.i18n.__; //translation functions
    const winkInspectorControls = wp.blockEditor.InspectorControls; //Block inspector wrapper
    const winkTextControl = wp.components.TextControl; //WordPress form inputs and server-side renderer
    const winkSelectControl = wp.components.SelectControl; //WordPress form inputs and server-side renderer
    const winkServerSideRender = wp.serverSideRender; //WordPress form inputs and server-side renderer
    const blockName = 'winkcontent';
    const iconEl = winkCreateElement('img', { width: 24, src: winkData.imgURL + 'icon.svg' });
    var layoutData = [{
            layout: 'ADD_ON_CARD', // now you know which “layout” to use for wink-content-loader
            id: '7d9189da-1a17-4632-a882-ac94b85be49d', // the identifier to inject into the web component
            name: 'Add On Cart', // the name you can use to populate the dropdown list
            placeholderImageUrl: winkData.imgURL+'placeholder-bg.png' // an image you can use as background image to fill out the Block similar to an image block  
        },{
            layout: 'PACKAGE_CARD', // now you know which “layout” to use for wink-content-loader
            id: '2b701f2a-cc11-44cd-a4ec-bc2f6e9889bb', // the identifier to inject into the web component
            name: 'Package Card', // the name you can use to populate the dropdown list
            placeholderImageUrl: winkData.imgURL+'placeholder-bg.png' // an image you can use as background image to fill out the Block similar to an image block  
    }];
    if (winkContentData) {
        layoutData = winkContentData;
        loadBlock();
        console.log(layoutData);
        // jQuery.getJSON(winkContentData.endpointURL, function(data) {
        //     // JSON result in `data` variable
        //     if (data) {
        //         layoutData = data;
        //     }
        //     loadBlock();
        // });
    } else {
        loadBlock();
    }

    function loadFormattedLayoutData(layoutData) {
        
        var formattedLayoutData = [{
            'label' : "Choose your travel inventory",
            'value' : ""
        }];
        jQuery.each(layoutData,function(index,value){
            var currentData = {
                'label' : value.name,
                'value' : value.id
            }
            formattedLayoutData.push(currentData);
        });
        return formattedLayoutData;
    }

    function loadBlock() {
        // layoutData.push({
        //     layout: 'RANKED_INVENTORY_GRID', // now you know which “layout” to use for wink-content-loader
        //     id: '0', // the identifier to inject into the web component
        //     name: 'Ranked Inventory Grid', // the name you can use to populate the dropdown list
        //     placeholderImageUrl: '' // an image you can use as background image to fill out the Block similar to an image block
        // });
        var formattedLayoutData = loadFormattedLayoutData(layoutData);
        winkRegisterBlockType( 'wink-blocks/'+blockName, {
            apiVersion: 3,
            title: wink__( 'WINK Content' ), // Block title.
            category:  winkData.blockCat, //category
            icon: iconEl,
            supports: {
                'multiple' : true
            },
            attributes:  {
                "layout" : {
                    default: '',
                },
                "layoutId" : {
                    default: '',
                },
                "background" : {
                    default: '',
                }
            },
            //display the post title
            edit(props){
                const winkBlockProps = wp.blockEditor.useBlockProps();
                const attributes =  props.attributes;
                const setAttributes =  props.setAttributes;

                //Function to update slug attribute
                function changeLayout(layoutId){
                    setAttributes({layoutId});
                    if (layoutId !== "") {
                        setLayoutData(layoutId);
                    } else {
                        var layout = "";
                        setAttributes({layout});
                        var background = "";
                        if (background) {
                            setAttributes({background});
                            attributes.background = "";
                        }
                    }
                }
                
                function setLayoutData(layoutid) {
                    background = '';
                    setAttributes({background});
                    if (layoutid.type == 'number') {
                        layoutid = layoutid.toString();
                    }
                    console.log(layoutid);
                    console.log(layoutData);
                    jQuery.each(layoutData,function(index,value){
                        if (value.id == layoutid) {
                            var layout = value.layout;
                            setAttributes({layout});
                            background = value.placeholderImageUrl;
                            if (background) {
                                setAttributes({background});
                                
                            }
                        }
                    });
                    
                }

                function changeBackground(background){
                    setAttributes({background});
                }

                if (attributes.layout === "") {
                    attributes.background = "";
                }
                if (winkData.mode == 'staging' || winkData.mode == 'development') {
                    var preview = winkCreateElement( winkServerSideRender, {
                        block: 'wink-blocks/'+blockName,
                        attributes: attributes,
                        key: 'winkPreview_'+blockName
                    } );
                } else {
                    var preview = winkCreateElement(
                        'div',
                            {
                                class: blockName
                            },
                            winkCreateElement(
                                'p',
                                    {
                                        
                                    },
                                    wink__('Ranked Inventory Grid')
                            ),
                            winkCreateElement(
                                'p',
                                    {
                                        class: 'noPreview' 
                                    },
                                    wink__('No Preview Available') 
                            ),
                            winkCreateElement(
                                'img',
                                {
                                    src: winkData.imgURL+'logo.svg',
                                    width: 160
                                }
                            ),
                    );
                    
                    if (attributes.layout === "") {
                        var preview = winkCreateElement(
                            'div',
                                {
                                    class: blockName
                                },
                                winkCreateElement(
                                    'p',
                                        {
                                            
                                        },
                                        wink__('Please select a layout in the block settings.')
                                ),
                                winkCreateElement(
                                    'img',
                                    {
                                        src: winkData.imgURL+'logo.svg',
                                    width: 160
                                    }
                                ),
                        );
                    }
                    else if (attributes.layout !== "RANKED_INVENTORY_GRID") {
                        preview = winkCreateElement(
                        'img',
                            {
                                src: attributes.background,
                                class: 'winkContentImg'
                            },
                        );
                    }
                }

                var backgroundURL = [];
                if(attributes.layout != '') {
                    backgroundURL = winkCreateElement(winkTextControl, {
                        value: attributes.background,
                        label: wink__( 'Background Image URL' ),
                        onChange: changeBackground,
                        type: 'text',
                        key: 'winkBackground'
                    })
                }

                var shortcodeAttributes = "";
                jQuery.each(attributes,function(index,value){
                    shortcodeAttributes += ' '+index+'=""';
                });
                const inspector = winkCreateElement( winkInspectorControls, {
                    key: 'winkInspector_'+blockName 
                },
                    [
                        winkCreateElement(
                            'div',
                            {
                                
                            },
                            wink__('We strongly recommend to use this block only in full-width content areas and not in columns.')
                        ),
                        winkCreateElement(winkSelectControl, {
                            value: attributes.layoutId,
                            label: wink__( 'Layout' ),
                            onChange: changeLayout,
                            type: 'select',
                            options: formattedLayoutData,
                            key: 'winkLayout'
                        }),
                        // backgroundURL,
                        // bgPosition,
                        // customBGPosition,
                        // bgSize,
                        // customBGSize,
                    ],
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
                            '['+blockName+shortcodeAttributes+']'
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
}
initwinkContent();
