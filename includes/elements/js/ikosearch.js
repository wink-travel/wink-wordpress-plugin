function initIkoSearch() {
    const ikoRegisterBlockType = wp.blocks.registerBlockType; //Blocks API
    const ikoCreateElement = wp.element.createElement; //React.createElement
    const iko__ = wp.i18n.__; //translation functions
    const ikoInspectorControls = wp.editor.InspectorControls; //Block inspector wrapper
    const ikoTextControl = wp.components.TextControl; //WordPress form inputs and server-side renderer
    const ikoServerSideRender = wp.components.ServerSideRender; //WordPress form inputs and server-side renderer
    const blockName = 'ikosearch';
    const iconEl = ikoCreateElement('img', { width: 100, src: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iNDAwLjAwMDAwMHB0IiBoZWlnaHQ9IjEzNS4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDQwMC4wMDAwMDAgMTM1LjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsMTM1LjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTYyMCAxMzM3IGMtMzcgLTE5IC01MiAtNTcgLTM5IC05NiAxOSAtNTUgODggLTcwIDEyNyAtMjggMzcgMzkgMjgKOTggLTE5IDEyMiAtMzQgMTggLTM3IDE4IC02OSAyeiIvPgo8cGF0aCBkPSJNMjU3IDEyNDIgYy0yMiAtMjUgLTIxIC03NSAxIC05NSA0MyAtMzkgMTEyIC04IDExMiA1MCAwIDUzIC03OCA4NAotMTEzIDQ1eiIvPgo8cGF0aCBkPSJNOTYyIDEyMzQgYy02NiAtNDYgLTM2IC0xNDMgNDMgLTE0NCA2NSAwIDEwMSA2MCA3MSAxMTggLTIzIDQ1IC03Mgo1NiAtMTE0IDI2eiIvPgo8cGF0aCBkPSJNMjAgMTAyMCBjLTExIC0xMSAtMjAgLTI4IC0yMCAtMzggMCAtMjYgMzIgLTUyIDYzIC01MiA0NCAwIDY0IDYyCjI5IDkzIC0yNSAyMiAtNDggMjEgLTcyIC0zeiIvPgo8cGF0aCBkPSJNMTIyNCA5OTAgYy0zMCAtMTIgLTU0IC00OSAtNTQgLTgzIDAgLTM3IDQ5IC04NyA4NSAtODcgOTAgMCAxMjMKMTI1IDQ0IDE2NSAtMzEgMTcgLTQ0IDE4IC03NSA1eiIvPgo8cGF0aCBkPSJNMTI1IDc0NyBjLTMgLTYgLTQgLTE3NiAtMyAtMzc3IGwzIC0zNjUgMjMgLTMgMjIgLTMgMCAzODAgYzAgMzc2IDAKMzgxIC0yMCAzODEgLTExIDAgLTIzIC02IC0yNSAtMTN6Ii8+CjxwYXRoIGQ9Ik0xNjg0IDc0NyBjLTMgLTkgLTQgLTEzNiAtMiAtMjg0IDMgLTI1MiA0IC0yNzAgMjUgLTMwOCA0MyAtODEgMTI1Ci0xMzkgMjA4IC0xNDcgMjYgLTMgMzAgMSAzMyAyMyAzIDI0IC0xIDI3IC0zOCAzMiAtNTEgNyAtMTAyIDQwIC0xMzggOTEgbC0yNwozOSAtNSAyODEgLTUgMjgxIC0yMyAzIGMtMTMgMiAtMjQgLTMgLTI4IC0xMXoiLz4KPHBhdGggZD0iTTM5NDUgNzQ4IGMtMyAtNyAtNCAtMTc3IC0zIC0zNzggbDMgLTM2NSAyNSAwIDI1IDAgMCAzNzUgMCAzNzUgLTIzCjMgYy0xMiAyIC0yNCAtMyAtMjcgLTEweiIvPgo8cGF0aCBkPSJNMCA2MjAgYzAgLTI4IDMgLTMxIDI4IC0yOCAyMSAyIDI3IDggMjcgMjggMCAyMCAtNiAyNiAtMjcgMjggLTI1IDMKLTI4IDAgLTI4IC0yOHoiLz4KPHBhdGggZD0iTTEzMTkgNjAxIGMtMzkgLTQwIC00MCAtODkgLTEgLTEzMiA2NyAtNzUgMTg2IC0xMiAxNjcgODggLTEzIDczCi0xMTEgOTkgLTE2NiA0NHoiLz4KPHBhdGggZD0iTTAgMjcwIGwwIC0yNzEgMjggMyAyNyAzIDAgMjY1IDAgMjY1IC0yNyAzIC0yOCAzIDAgLTI3MXoiLz4KPHBhdGggZD0iTTM5MyA0MDcgYy0xODMgLTEyNSAtMTkyIC0xMzQgLTE5MyAtMTY0IDAgLTM5IDMgLTM5IDQwIC0xMyBsMjggMjAKMTU3IC0xMjAgYzg3IC02NiAxNTkgLTEyMCAxNjEgLTEyMCAyIDAgNCAxNSA0IDM0IDAgMzMgLTggNDEgLTEyNyAxMzIgLTcxIDUzCi0xMzIgMTAwIC0xMzYgMTA0IC00IDQgNTMgNDcgMTI3IDk2IDEzNCA4OCAxMzYgODkgMTM2IDEyNyAwIDIwIC0xIDM3IC0yIDM2Ci0yIDAgLTg5IC02MCAtMTk1IC0xMzJ6Ii8+CjxwYXRoIGQ9Ik03ODkgNTI3IGMtMTQ5IC01NiAtMjIxIC0yMzYgLTE1MCAtMzc0IDI2IC01MSA5OCAtMTE3IDE0NyAtMTM0IDUyCi0xOCAxNTAgLTE2IDE5NSA0IDU0IDIyIDExNCA4MSAxMzkgMTM1IDI4IDU4IDMxIDE1OCA2IDIxNyAtMjEgNTAgLTc5IDExMAotMTMxIDEzNyAtNTAgMjYgLTE1NiAzNCAtMjA2IDE1eiBtMTk5IC03NCBjMTIwIC02OSAxMzcgLTI0MiAzMyAtMzM2IC01MCAtNDUKLTEwMCAtNjEgLTE2OCAtNTQgLTY0IDcgLTExMiAzNSAtMTUwIDkxIC0zNiA1MCAtNDQgMTU0IC0xOCAyMDcgNjAgMTE4IDE4OQoxNTggMzAzIDkyeiIvPgo8cGF0aCBkPSJNMTc2MCA1MTUgYzAgLTI1IDEgLTI1IDgwIC0yNSA3OSAwIDgwIDAgODAgMjUgMCAyNSAtMSAyNSAtODAgMjUKLTc5IDAgLTgwIDAgLTgwIC0yNXoiLz4KPHBhdGggZD0iTTIwMDIgMjczIGwzIC0yNjggMjUgMCAyNSAwIDMgMjY4IDIgMjY3IC0zMCAwIC0zMCAwIDIgLTI2N3oiLz4KPHBhdGggZD0iTTIxNDYgNTI0IGMtNTUgLTIwIC02NiAtMzIgLTY2IC03MCBsMCAtMzMgMzcgMjUgYzIwIDEzIDQ4IDI3IDYyIDMwCjU2IDEzIDYxIDE2IDYxIDQwIDAgMjAgLTUgMjQgLTI3IDIzIC0xNiAwIC00NiAtNyAtNjcgLTE1eiIvPgo8cGF0aCBkPSJNMjM5NSA1MTcgYy0xMDMgLTQ5IC0xNTkgLTEzNCAtMTU5IC0yNDcgMCAtODAgMjcgLTE0MyA4NiAtMTk1IDU2Ci01MSAxMDEgLTY4IDE3OCAtNjggNzQgMCAxMjAgMTYgMTczIDYyIGwzNiAzMiAzIC00OCBjMyAtNDQgNSAtNDggMjggLTQ4IGwyNQowIDMgMjY4IDIgMjY3IC0zMCAwIC0zMCAwIDAgLTE2MCBjMCAtMTc1IC02IC0yMDQgLTU2IC0yNTQgLTEwNyAtMTA3IC0yNzYKLTc5IC0zNDYgNTggLTE2IDMxIC0xOSA1MyAtMTYgMTAyIDUgNzcgMzggMTMxIDEwNSAxNzEgNDMgMjUgNTggMjggMTcxIDMxCjExOSA0IDEyMyA1IDEyMCAyNiAtMyAyMCAtOSAyMSAtMTIzIDI0IC0xMDggMiAtMTI1IDAgLTE3MCAtMjF6Ii8+CjxwYXRoIGQ9Ik0yODIzIDUyOCBjMyAtNyA0OSAtMTIxIDEwMiAtMjUzIDU0IC0xMzIgMTAxIC0yNDggMTA2IC0yNTcgMTIgLTIxCjkxIC0yNSAxMDYgLTUgMTAgMTIgMjEzIDUxMCAyMTMgNTIxIDAgMTQgLTUwIDQgLTU5IC0xMiAtNSAtMTAgLTUxIC0xMjMgLTEwMQotMjUyIC01MCAtMTI5IC05NSAtMjM5IC05OSAtMjQzIC01IC01IC01NSAxMDkgLTExMSAyNTIgLTEwMiAyNTkgLTEwMyAyNjEKLTEzMyAyNjEgLTE5IDAgLTI3IC00IC0yNCAtMTJ6Ii8+CjxwYXRoIGQ9Ik0zNTA1IDUxNiBjLTYwIC0yOCAtMTE4IC04NiAtMTQxIC0xNDEgLTEwIC0yMyAtMTcgLTY3IC0xNyAtMTA1IDAKLTEwNiA1MyAtMTkwIDE1MyAtMjQxIDM4IC0xOSA2NSAtMjMgMTY4IC0yNyBsMTIyIC00IDAgMzEgMCAzMSAtMTEwIDAgYy0xMjEKMCAtMTY1IDEyIC0yMTMgNTggLTI5IDI3IC01NyA3MiAtNTcgOTIgMCA3IDc4IDEwIDIzNSAxMCBsMjM1IDAgMCA0MyBjMCAyMDAKLTIwMSAzMzUgLTM3NSAyNTN6IG0yMDAgLTUzIGM2MiAtMzIgMTA5IC05NSAxMTYgLTE1OCBsNCAtMzAgLTIxMiAtMyAtMjEzIC0yCjAgMjMgYzAgMTMgMTIgNDcgMjYgNzYgNTMgMTA1IDE3NSAxNDYgMjc5IDk0eiIvPgo8cGF0aCBkPSJNMTM4NCAyMjYgYy00NCAtMTkgLTY0IC01MyAtNjQgLTEwNiAxIC02OSA0MyAtMTEwIDExNSAtMTEwIDUyIDAKMTA1IDUzIDEwNSAxMDQgMCA1MSAtMjMgOTAgLTY0IDExMCAtNDEgMTkgLTUxIDE5IC05MiAyeiIvPgo8L2c+Cjwvc3ZnPgo='});
    ikoRegisterBlockType( 'ikotravel-blocks/'+blockName, {
        title: iko__( 'iko.travel Search Button' ), // Block title.
        category:  ikoTravelData.blockCat, //category
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
            const attributes =  props.attributes;
            const setAttributes =  props.setAttributes;
            
            // const preview = ikoCreateElement( ikoServerSideRender, {
            //     block: 'ikotravel-blocks/'+blockName,
            //     attributes: attributes,
            //     key: 'ikoTravelPreview_'+blockName
            // } );
            if (ikoTravelData.mode == 'staging' || ikoTravelData.mode == 'development') {
                var preview = ikoCreateElement( ikoServerSideRender, {
                    block: 'ikotravel-blocks/'+blockName,
                    attributes: attributes,
                    key: 'ikoTravelPreview_'+blockName
                } );
            } else {
                var preview = ikoCreateElement(
                    'button',
                    {
                        class: blockName
                    },
                    ikoCreateElement(
                        'img',
                        {
                            src: ikoTravelData.imgURL+'search.svg'
                        }
                    )
                );
            }
            const inspector = ikoCreateElement( ikoInspectorControls, {
                key: 'ikoTravelInspector_'+blockName 
            },
                [
                    ikoCreateElement(
                        'p',
                        {},
                        iko__('Alternatively to this block, you can also use the following shortcode:')
                    ) 
                ],
                [
                    ikoCreateElement(
                        'p',
                        {},
                        '['+blockName+']'
                    ) 
                ]
            );

            return ikoCreateElement(
                'div',
                {},
                // Children of the main div as an array
                [ preview, inspector ]
            );
        },
        save(){
            return null;//save has to exist. This all we need
        }
    });
}

initIkoSearch();
