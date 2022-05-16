function initIkoItinerary() {
    const ikoRegisterBlockType = wp.blocks.registerBlockType; //Blocks API
    const ikoCreateElement = wp.element.createElement; //React.createElement
    const iko__ = wp.i18n.__; //translation functions
    const ikoInspectorControls = wp.editor.InspectorControls; //Block inspector wrapper
    const ikoTextControl = wp.components.TextControl; //WordPress form inputs and server-side renderer
    const ikoServerSideRender = wp.components.ServerSideRender; //WordPress form inputs and server-side renderer
    const blockName = 'ikoitinerary';
    const iconEl = ikoCreateElement('img', { width: 24, src: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDE2LjAuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPgo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IgoJIHdpZHRoPSI5NnB4IiBoZWlnaHQ9Ijk2cHgiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgOTYgOTYiIHhtbDpzcGFjZT0icHJlc2VydmUiPgo8Zz4KCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfMV8iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iNzguNzUyOSIgeTE9IjEwMS4yNjQyIiB4Mj0iMTcuMjQ4NSIgeTI9Ii01LjI2NDYiPgoJCTxzdG9wICBvZmZzZXQ9IjAiIHN0eWxlPSJzdG9wLWNvbG9yOiMwQjBGN0QiLz4KCQk8c3RvcCAgb2Zmc2V0PSIxIiBzdHlsZT0ic3RvcC1jb2xvcjojOUUzOUZCIi8+Cgk8L2xpbmVhckdyYWRpZW50PgoJPHBhdGggZmlsbD0idXJsKCNTVkdJRF8xXykiIGQ9Ik05Niw4NC44ODlDOTYsOTEuMDI1LDkxLjAyNSw5Niw4NC44OSw5NkgxMS4xMUM0Ljk3NCw5NiwwLDkxLjAyNSwwLDg0Ljg4OVYxMS4xMTIKCQlDMCw0Ljk3NSw0Ljk3NCwwLDExLjExLDBIODQuODlDOTEuMDI1LDAsOTYsNC45NzUsOTYsMTEuMTEyVjg0Ljg4OXoiLz4KCTxnPgoJCTxwYXRoIGZpbGw9IiNGRkZGRkYiIGQ9Ik0xNC40MDQsNTEuNDY2djI0LjkyN2gtMi40OTJWNTEuNDY2SDE0LjQwNHoiLz4KCQk8cmVjdCB4PSIxNy40NDEiIHk9IjQxLjQ5NSIgZmlsbD0iI0ZGRkZGRiIgd2lkdGg9IjIuNDkxIiBoZWlnaHQ9IjM0Ljg5NyIvPgoJCTxwYXRoIGZpbGw9IiNGRkZGRkYiIGQ9Ik01Mi44NzIsNTEuNDY2YzMuNDM4LDAsNi4zNzQsMS4yMTgsOC44MDgsMy42NWMyLjQzNSwyLjQzNywzLjY1MSw1LjM3NCwzLjY1MSw4LjgxMwoJCQljMCwzLjQ0LTEuMjE3LDYuMzc5LTMuNjUxLDguODEzYy0yLjQzNCwyLjQzNC01LjM2OSwzLjY1LTguODA4LDMuNjVjLTMuNDQyLDAtNi4zODItMS4yMTYtOC44MTMtMy42NQoJCQljLTIuNDM4LTIuNDM0LTMuNjUzLTUuMzczLTMuNjUzLTguODEzYzAtMy40MzksMS4yMTYtNi4zNzYsMy42NTMtOC44MTNDNDYuNDksNTIuNjg0LDQ5LjQzLDUxLjQ2Niw1Mi44NzIsNTEuNDY2eiBNNTIuODcyLDczLjkwMgoJCQljMi43NTUsMCw1LjEwNy0wLjk3Niw3LjA1MS0yLjkxOGMxLjk0NC0xLjk0MywyLjkxOC00LjI5NCwyLjkxOC03LjA1NGMwLTIuNzU1LTAuOTc0LTUuMTEtMi45MTgtNy4wNTQKCQkJYy0xLjk0My0xLjk0NC00LjI5Ni0yLjkxNS03LjA1MS0yLjkxNWMtMi43NjQsMC01LjExNSwwLjk3LTcuMDU4LDIuOTE1Yy0xLjk0NCwxLjk0NC0yLjkxNyw0LjI5OS0yLjkxNyw3LjA1NAoJCQljMCwyLjc2LDAuOTczLDUuMTExLDIuOTE3LDcuMDU0QzQ3Ljc1Nyw3Mi45MjYsNTAuMTA4LDczLjkwMiw1Mi44NzIsNzMuOTAyeiIvPgoJCTxwb2x5Z29uIGZpbGw9IiNGRkZGRkYiIHBvaW50cz0iMjMuOTI1LDY0LjYzNSAyNC43ODMsNjEuODIzIDM5LjUyNiw3My4wMTUgMzkuNTI2LDc2LjQwMiAJCSIvPgoJCTxwb2x5Z29uIGZpbGw9IiNGRkZGRkYiIHBvaW50cz0iMjEuMjI0LDYzLjkxNyAyMS4yMjQsNjcuMDI5IDM5LjUyNiw1NC42OTUgMzkuNTI2LDUxLjQ2NiAJCSIvPgoJCTxyZWN0IHg9IjExLjkxMiIgeT0iNDYuNDU4IiBmaWxsPSIjRkZGRkZGIiB3aWR0aD0iMi40OTIiIGhlaWdodD0iMi40OTUiLz4KCQkKCQkJPGxpbmVhckdyYWRpZW50IGlkPSJTVkdJRF8yXyIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiIHgxPSItMjAzLjI5MyIgeTE9Ijk5NC43MTgzIiB4Mj0iLTYxOC4xNjk1IiB5Mj0iMjc2LjEzMTIiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC45ODI0IC0wLjE4NjcgMC4xODY3IDAuOTgyNCAxNzguMzUyNSAtODYxLjc2ODMpIj4KCQkJPHN0b3AgIG9mZnNldD0iMC4xMjgxIiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGIi8+CgkJCTxzdG9wICBvZmZzZXQ9IjAuMzAwNSIgc3R5bGU9InN0b3AtY29sb3I6I0ZGRkZGRjtzdG9wLW9wYWNpdHk6MC44Ii8+CgkJPC9saW5lYXJHcmFkaWVudD4KCQk8cGF0aCBmaWxsPSJ1cmwoI1NWR0lEXzJfKSIgZD0iTTc2LjcxNSw1Ni40OTVjMC4wNTgsMCwwLjExNS0wLjAxLDAuMTc2LTAuMDFjMi42MTctMC4wODcsNC42NzMtMi4yODksNC41ODgtNC45MTYKCQkJYy0wLjA5Ni0yLjYyNS0yLjMtNC42ODMtNC45MjgtNC41ODhjLTIuNjE2LDAuMDg3LTQuNjc0LDIuMjg5LTQuNTg2LDQuOTE4QzcyLjA2MSw1NC40NjcsNzQuMTY4LDU2LjQ5NSw3Ni43MTUsNTYuNDk1eiIvPgoJCQoJCQk8bGluZWFyR3JhZGllbnQgaWQ9IlNWR0lEXzNfIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeDE9Ii0xOTcuNDUyMSIgeTE9Ijk5MS4zNjY3IiB4Mj0iLTYxMi40Mzk5IiB5Mj0iMjcyLjU4NyIgZ3JhZGllbnRUcmFuc2Zvcm09Im1hdHJpeCgwLjk4MjQgLTAuMTg2NyAwLjE4NjcgMC45ODI0IDE3OC4zNTI1IC04NjEuNzY4MykiPgoJCQk8c3RvcCAgb2Zmc2V0PSIwLjEyODEiIHN0eWxlPSJzdG9wLWNvbG9yOiNGRkZGRkYiLz4KCQkJPHN0b3AgIG9mZnNldD0iMC4zMDA1IiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGO3N0b3Atb3BhY2l0eTowLjciLz4KCQk8L2xpbmVhckdyYWRpZW50PgoJCTxwYXRoIGZpbGw9InVybCgjU1ZHSURfM18pIiBkPSJNNzQuMjQyLDMyLjI4NmMtMS4xMTctMi4wMTQtMy42NTYtMi43NDktNS42NzItMS42MzFjLTIuMDIyLDEuMTE4LTIuNzQ4LDMuNjU5LTEuNjMsNS42NzUKCQkJYzAuNzU2LDEuMzc2LDIuMTg0LDIuMTUyLDMuNjUxLDIuMTUyYzAuNjg5LDAsMS4zNzMtMC4xNjYsMi4wMTktMC41MTdDNzQuNjI4LDM2Ljg0Nyw3NS4zNjEsMzQuMzAyLDc0LjI0MiwzMi4yODZ6Ii8+CgkJCgkJCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfNF8iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iLTIzMi4zNzExIiB5MT0iMTAxMS4zNzQ1IiB4Mj0iLTY0Ny4zNjIxIiB5Mj0iMjkyLjU4OTEiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC45ODI0IC0wLjE4NjcgMC4xODY3IDAuOTgyNCAxNzguMzUyNSAtODYxLjc2ODMpIj4KCQkJPHN0b3AgIG9mZnNldD0iMC4xMjgxIiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGIi8+CgkJCTxzdG9wICBvZmZzZXQ9IjAuMzAwNSIgc3R5bGU9InN0b3AtY29sb3I6I0ZGRkZGRjtzdG9wLW9wYWNpdHk6MC4zIi8+CgkJPC9saW5lYXJHcmFkaWVudD4KCQk8cGF0aCBmaWxsPSJ1cmwoI1NWR0lEXzRfKSIgZD0iTTEyLjYyNywyOS4wODNjLTAuOTgsMS4wMjctMC45NDcsMi42NjUsMC4wNzgsMy42NDljMC41MDEsMC40NzcsMS4xNDksMC43MTUsMS43ODUsMC43MTUKCQkJYzAuNjg1LDAsMS4zNjQtMC4yNjgsMS44NzItMC43OThjMC45OC0xLjAzLDAuOTQ2LTIuNjYxLTAuMDgyLTMuNjUxQzE1LjI0OSwyOC4wMTMsMTMuNjEyLDI4LjA1LDEyLjYyNywyOS4wODN6Ii8+CgkJCgkJCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfNV8iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iLTE5OC4zODk2IiB5MT0iOTkxLjcxMjQiIHgyPSItNjEzLjMwMjgiIHkyPSIyNzMuMDYxOSIgZ3JhZGllbnRUcmFuc2Zvcm09Im1hdHJpeCgwLjk4MjQgLTAuMTg2NyAwLjE4NjcgMC45ODI0IDE3OC4zNTI1IC04NjEuNzY4MykiPgoJCQk8c3RvcCAgb2Zmc2V0PSIwLjEyODEiIHN0eWxlPSJzdG9wLWNvbG9yOiNGRkZGRkYiLz4KCQkJPHN0b3AgIG9mZnNldD0iMC4zMDA1IiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGO3N0b3Atb3BhY2l0eTowLjYiLz4KCQk8L2xpbmVhckdyYWRpZW50PgoJCTxwYXRoIGZpbGw9InVybCgjU1ZHSURfNV8pIiBkPSJNNjEuMDM2LDE5LjAzOWMtMS43NTItMS4xOTQtNC4xNDctMC43NDMtNS4zNDYsMS4wMTVjLTEuMTkzLDEuNzU1LTAuNzQ0LDQuMTQ3LDEuMDE0LDUuMzQzCgkJCWMwLjY2NiwwLjQ1MywxLjQxNiwwLjY2OCwyLjE2MSwwLjY2OGMxLjIyOCwwLDIuNDM4LTAuNTg1LDMuMTgxLTEuNjc1QzYzLjI0MSwyMi42MzMsNjIuNzkyLDIwLjI0Miw2MS4wMzYsMTkuMDM5eiIvPgoJCQoJCQk8bGluZWFyR3JhZGllbnQgaWQ9IlNWR0lEXzZfIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeDE9Ii0yMTkuMzE3NCIgeTE9IjEwMDMuNzQxNyIgeDI9Ii02MzQuMTI2IiB5Mj0iMjg1LjI3MjIiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC45ODI0IC0wLjE4NjcgMC4xODY3IDAuOTgyNCAxNzguMzUyNSAtODYxLjc2ODMpIj4KCQkJPHN0b3AgIG9mZnNldD0iMC4xMjgxIiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGIi8+CgkJCTxzdG9wICBvZmZzZXQ9IjAuMzAwNSIgc3R5bGU9InN0b3AtY29sb3I6I0ZGRkZGRjtzdG9wLW9wYWNpdHk6MC40Ii8+CgkJPC9saW5lYXJHcmFkaWVudD4KCQk8cGF0aCBmaWxsPSJ1cmwoI1NWR0lEXzZfKSIgZD0iTTI0Ljk1MSwxOC4wODJjLTEuNjQ2LDAuNTg1LTIuNTE0LDIuMzk4LTEuOTMxLDQuMDQyYzAuNDY3LDEuMzAxLDEuNjg0LDIuMTEyLDIuOTg3LDIuMTEyCgkJCWMwLjM1MywwLDAuNzA1LTAuMDYsMS4wNTYtMC4xODVjMS42NDctMC41ODEsMi41MTUtMi4zODksMS45MzQtNC4wNDFDMjguNDA5LDE4LjM2NSwyNi42MDQsMTcuNDk5LDI0Ljk1MSwxOC4wODJ6Ii8+CgkJCgkJCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfN18iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iLTIwNi42NTQzIiB5MT0iOTk2LjQxNTUiIHgyPSItNjIxLjQyNzIiIHkyPSIyNzguMDA3OCIgZ3JhZGllbnRUcmFuc2Zvcm09Im1hdHJpeCgwLjk4MjQgLTAuMTg2NyAwLjE4NjcgMC45ODI0IDE3OC4zNTI1IC04NjEuNzY4MykiPgoJCQk8c3RvcCAgb2Zmc2V0PSIwLjEyODEiIHN0eWxlPSJzdG9wLWNvbG9yOiNGRkZGRkYiLz4KCQkJPHN0b3AgIG9mZnNldD0iMC4zMDA1IiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGO3N0b3Atb3BhY2l0eTowLjUiLz4KCQk8L2xpbmVhckdyYWRpZW50PgoJCTxwYXRoIGZpbGw9InVybCgjU1ZHSURfN18pIiBkPSJNNDIuODM3LDEzLjk2Yy0yLjAyOC0wLjI2LTMuODg5LDEuMTctNC4xNTEsMy4yYy0wLjI2MiwyLjAzMiwxLjE3NSwzLjg4OSwzLjIwNSw0LjE1NAoJCQljMC4xNiwwLjAyMSwwLjMyLDAuMDI3LDAuNDc5LDAuMDI3YzEuODM3LDAsMy40My0xLjM2MywzLjY3Ny0zLjIzNEM0Ni4zMSwxNi4wODQsNDQuODcxLDE0LjIyNiw0Mi44MzcsMTMuOTZ6Ii8+CgkJCgkJCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfOF8iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iLTIxMi45OTYxIiB5MT0iMTAwMC4xMjA2IiB4Mj0iLTYyNy44NDc3IiB5Mj0iMjgxLjU3NjYiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC45ODI0IC0wLjE4NjcgMC4xODY3IDAuOTgyNCAxNzguMzUyNSAtODYxLjc2ODMpIj4KCQkJPHN0b3AgIG9mZnNldD0iMC4xMjgxIiBzdHlsZT0ic3RvcC1jb2xvcjojRkZGRkZGIi8+CgkJCTxzdG9wICBvZmZzZXQ9IjAuMzAwNSIgc3R5bGU9InN0b3AtY29sb3I6I0ZGRkZGRjtzdG9wLW9wYWNpdHk6MC45Ii8+CgkJPC9saW5lYXJHcmFkaWVudD4KCQk8cGF0aCBmaWxsPSJ1cmwoI1NWR0lEXzhfKSIgZD0iTTc4LjU1Niw2NS40NzdjLTIuOTE2LDAtNS4zODQsMi40NjYtNS4zODQsNS40NThjMCwyLjk5MiwyLjQ2OCw1LjQ1OCw1LjM4NCw1LjQ1OAoJCQljMi45ODksMCw1LjUzMi0yLjQ2Niw1LjUzMi01LjQ1OEM4NC4wODgsNjcuOTQzLDgxLjU0NSw2NS40NzcsNzguNTU2LDY1LjQ3N3oiLz4KCTwvZz4KPC9nPgo8L3N2Zz4K'});
    ikoRegisterBlockType( 'ikotravel-blocks/'+blockName, {
        title: iko__( 'iko.travel Itinerary Button' ), // Block title.
        category:  ikoTravelData.blockCat, //category
        icon: iconEl,
        supports: {
            'multiple' : true
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
            var date = new Date();
            var mm = date.getMonth() + 1; // getMonth() is zero-based
            var dd = date.getDate();
            var yy = date.getFullYear().toString().substr(-2);

            var dateTomorrow = new Date(date);
            dateTomorrow.setDate(dateTomorrow.getDate() + 1)
            var mmTomorrow = dateTomorrow.getMonth() + 1; // getMonth() is zero-based
            var ddTomorrow = dateTomorrow.getDate();
            var yyTomorrow = dateTomorrow.getFullYear().toString().substr(-2);
            
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
                            src: ikoTravelData.imgURL+'calendar.svg'
                        }
                    ),
                    ikoCreateElement(
                        'div',
                        {
                            
                        },
                        dd+'/'+mm+'/'+yy+' - '+ddTomorrow+'/'+mmTomorrow+'/'+yyTomorrow+', '+iko__(
                            '1 room, 2 guests')
                    ),
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

initIkoItinerary();
