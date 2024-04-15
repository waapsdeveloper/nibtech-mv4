            <!-- Country-selector modal-->
			<div class="modal fade" id="country-selector">
				<div class="modal-dialog modal-dialog-centered" role="document">
					<div class="modal-content">
						<div class="modal-header border-bottom">
							<h6 class="modal-title">Choose Country</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal" type="button"><span aria-hidden="true">×</span></button>
						</div>
						<div class="modal-body">
							<ul class="row p-3">
								<li class="col-lg-6 mb-2">
									<a href="{{url('lang/en')}}" class="btn btn-country btn-lg btn-block @if(app()->getLocale() == 'en') active @endif">
										<span class="country-selector m-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" width="20" height="15">

                                                <g transform="matrix(0.03125 0 0 0.03125 0 0)">
                                                    <g>
                                                        <g>
                                                            <path d="M0 0L912 0L912 37L0 37L0 0zM0 73.9L912 73.9L912 110.9L0 110.9zM0 147.70001L912 147.70001L912 184.70001L0 184.70001zM0 221.50002L912 221.50002L912 258.5L0 258.5zM0 295.5L912 295.5L912 332.3L0 332.3zM0 369.2L912 369.2L912 406.2L0 406.2zM0 443L912 443L912 480L0 480z" stroke="none" fill="#BD3D44" fill-rule="nonzero"/>
                                                            <path d="M0 37L912 37L912 73.9L0 73.9L0 37zM0 110.8L912 110.8L912 147.70001L0 147.70001zM0 184.6L912 184.6L912 221.6L0 221.6zM0 258.5L912 258.5L912 295.5L0 295.5zM0 332.3L912 332.3L912 369.3L0 369.3zM0 406.09998L912 406.09998L912 443.09998L0 443.09998z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        </g>
                                                        <path d="M0 0L364.8 0L364.8 258.5L0 258.5L0 0z" stroke="none" fill="#192F5D" fill-rule="nonzero"/>
                                                        <path d="M30.4 11L33.8 21.3L44.4 21.3L35.800003 27.599998L39.100002 37.899998L30.400002 31.499998L21.800001 37.8L25 27.6L16.3 21.3L27.199999 21.3L30.4 11zM91.2 11L94.5 21.3L105.3 21.3L96.600006 27.599998L99.8 37.899998L91.200005 31.499998L82.50001 37.8L85.80001 27.599998L77.20001 21.3L87.80001 21.3zM152 11L155.3 21.3L166 21.3L157.4 27.599998L160.7 37.899998L152 31.499998L143.3 37.8L146.6 27.599998L137.90001 21.3L148.70001 21.3zM212.8 11L216.1 21.3L226.90001 21.3L218.20001 27.599998L221.50002 37.899998L212.80002 31.499998L204.10002 37.8L207.50002 27.599998L198.70001 21.3L209.40001 21.3zM273.6 11L276.9 21.3L287.6 21.3L279 27.599998L282.3 37.899998L273.59998 31.499998L264.89996 37.8L268.19995 27.599998L259.59995 21.3L270.29996 21.3zM334.4 11L337.69998 21.3L348.49997 21.3L339.69998 27.599998L343.09998 37.899998L334.39996 31.499998L325.69995 37.8L329.09995 27.599998L320.29996 21.3L331.09995 21.3zM60.8 37L64.1 47.2L75 47.2L66.3 53.4L69.5 63.7L61 57.4L52.3 63.7L55.399998 53.4L47 47.2L57.7 47.2zM121.6 37L125 47.2L135.7 47.2L126.899994 53.4L130.29999 63.7L121.59999 57.4L112.899994 63.7L116.2 53.4L107.5 47.2L118.3 47.2zM182.4 37L185.7 47.2L196.5 47.2L187.8 53.4L191.1 63.7L182.40001 57.4L173.70001 63.7L177.00002 53.4L168.40001 47.2L179 47.2zM243.2 37L246.59999 47.2L257.3 47.2L248.49998 53.4L251.89998 63.7L243.19998 57.4L234.59998 63.7L237.79997 53.4L229.09998 47.2L240 47.2zM304 37L307.3 47.2L318.09998 47.2L309.39996 53.4L312.69995 63.7L303.99994 57.4L295.29993 63.7L298.5999 53.4L289.9999 47.2L300.69992 47.2zM30.4 62.6L33.8 73L44.4 73L35.800003 79.3L39.100002 89.5L30.400002 83.2L21.800001 89.5L25 79.3L16.3 73L27.199999 73zM91.2 62.6L94.5 73L105.3 73L96.600006 79.3L99.8 89.5L91.200005 83.2L82.50001 89.5L85.80001 79.2L77.20001 72.899994L87.80001 72.899994zM152 62.6L155.3 72.9L166 72.9L157.4 79.200005L160.7 89.4L152 83.1L143.3 89.4L146.6 79.1L137.90001 72.799995L148.70001 72.799995zM212.8 62.6L216.1 72.9L226.90001 72.9L218.20001 79.200005L221.50002 89.4L212.80002 83.1L204.10002 89.4L207.50002 79.1L198.70001 72.799995L209.40001 72.799995zM273.6 62.6L276.9 72.9L287.6 72.9L279 79.200005L282.3 89.4L273.59998 83.1L264.89996 89.4L268.19995 79.1L259.59995 72.799995L270.29996 72.799995zM334.4 62.6L337.69998 72.9L348.49997 72.9L339.69998 79.200005L343.09998 89.4L334.39996 83.1L325.69995 89.4L329.09995 79.1L320.29996 72.799995L331.09995 72.799995zM60.8 88.6L64.1 98.799995L75 98.799995L66.3 105.1L69.600006 115.4L60.900005 109L52.200005 115.3L55.500004 105.100006L46.9 98.8L57.600002 98.8zM121.6 88.6L125 98.799995L135.7 98.799995L126.899994 105.1L130.29999 115.4L121.59999 109L112.899994 115.3L116.2 105.100006L107.5 98.8L118.3 98.8zM182.4 88.6L185.7 98.799995L196.5 98.799995L187.8 105.1L191.1 115.4L182.40001 109L173.70001 115.3L177.00002 105.100006L168.40001 98.8L179 98.8zM243.2 88.6L246.59999 98.799995L257.3 98.799995L248.59999 105.1L251.9 115.4L243.2 109L234.59999 115.3L237.79999 105.100006L229.09999 98.8L240 98.8zM304 88.6L307.3 98.799995L318.09998 98.799995L309.39996 105.1L312.69995 115.4L303.99994 109L295.29993 115.3L298.5999 105.100006L289.9999 98.8L300.69992 98.8zM30.4 114.5L33.8 124.7L44.4 124.7L35.800003 131L39.100002 141.3L30.400002 134.90001L21.800001 141.20001L25 131L16.3 124.7L27.199999 124.7zM91.2 114.5L94.5 124.7L105.3 124.7L96.600006 131L99.8 141.2L91.200005 134.9L82.50001 141.2L85.80001 131L77.20001 124.7L87.80001 124.7zM152 114.5L155.3 124.7L166 124.7L157.4 131L160.7 141.3L152 134.90001L143.3 141.20001L146.6 131.00002L137.90001 124.70001L148.70001 124.70001zM212.8 114.5L216.1 124.7L226.90001 124.7L218.20001 131L221.50002 141.3L212.80002 134.90001L204.10002 141.20001L207.50002 131.00002L198.70001 124.70001L209.40001 124.70001zM273.6 114.5L276.9 124.7L287.6 124.7L279 131L282.3 141.3L273.59998 134.90001L264.89996 141.20001L268.19995 131.00002L259.59995 124.70001L270.29996 124.70001zM334.4 114.5L337.69998 124.7L348.49997 124.7L339.69998 131L343.09998 141.3L334.39996 134.90001L325.69995 141.20001L329 131L320.2 124.7L331 124.7zM60.8 140.3L64.1 150.6L75 150.6L66.3 156.8L69.600006 167.1L60.900005 160.70001L52.200005 167.1L55.500004 156.8L46.9 150.5L57.600002 150.5zM121.6 140.3L125 150.6L135.7 150.6L126.899994 156.8L130.29999 167.1L121.59999 160.70001L112.899994 167.1L116.2 156.8L107.5 150.5L118.3 150.5zM182.4 140.3L185.7 150.6L196.5 150.6L187.8 156.8L191.1 167.1L182.40001 160.70001L173.70001 167.1L177.00002 156.8L168.40001 150.5L179 150.5zM243.2 140.3L246.59999 150.6L257.3 150.6L248.59999 156.8L251.9 167.1L243.2 160.70001L234.59999 167.1L237.79999 156.8L229.09999 150.5L240 150.5zM304 140.3L307.3 150.6L318.09998 150.6L309.39996 156.8L312.69995 167.1L303.99994 160.70001L295.29993 167.1L298.5999 156.8L289.9999 150.5L300.69992 150.5zM30.4 166.1L33.8 176.40001L44.4 176.40001L35.800003 182.70001L39.100002 192.80002L30.400002 186.60002L21.800001 192.80002L25.000002 182.60002L16.300003 176.30002L27.200003 176.30002zM91.2 166.1L94.5 176.40001L105.3 176.40001L96.600006 182.70001L99.90001 192.80002L91.20001 186.60002L82.500015 192.80002L85.90002 182.60002L77.20002 176.30002L87.80002 176.30002zM152 166.1L155.3 176.40001L166 176.40001L157.4 182.70001L160.7 192.80002L152 186.60002L143.3 192.80002L146.6 182.60002L137.90001 176.30002L148.70001 176.30002zM212.8 166.1L216.1 176.40001L226.90001 176.40001L218.20001 182.70001L221.50002 192.80002L212.80002 186.60002L204.10002 192.80002L207.50002 182.60002L198.70001 176.30002L209.40001 176.30002zM273.6 166.1L276.9 176.40001L287.6 176.40001L279 182.70001L282.3 192.80002L273.59998 186.60002L264.89996 192.80002L268.19995 182.60002L259.59995 176.30002L270.29996 176.30002zM334.4 166.1L337.69998 176.40001L348.49997 176.40001L339.69998 182.70001L343.09998 192.80002L334.39996 186.60002L325.69995 192.80002L329.09995 182.60002L320.29996 176.30002L331.09995 176.30002zM60.8 192L64.1 202.2L75 202.2L66.3 208.5L69.600006 218.8L60.900005 212.40001L52.200005 218.70001L55.500004 208.50002L46.9 202.20001L57.600002 202.20001zM121.6 192L125 202.2L135.7 202.2L126.899994 208.5L130.29999 218.8L121.59999 212.40001L112.899994 218.70001L116.2 208.50002L107.5 202.20001L118.3 202.20001zM182.4 192L185.7 202.2L196.5 202.2L187.8 208.5L191.1 218.8L182.40001 212.40001L173.70001 218.70001L177.00002 208.50002L168.40001 202.20001L179 202.20001zM243.2 192L246.59999 202.2L257.3 202.2L248.59999 208.5L251.9 218.8L243.2 212.40001L234.59999 218.70001L237.79999 208.50002L229.09999 202.20001L240 202.20001zM304 192L307.3 202.2L318.09998 202.2L309.39996 208.5L312.69995 218.8L303.99994 212.40001L295.29993 218.70001L298.5999 208.50002L289.9999 202.20001L300.69992 202.20001zM30.4 217.9L33.8 228.09999L44.4 228.09999L35.800003 234.4L39.100002 244.59999L30.400002 238.29999L21.800001 244.59999L25.000002 234.29999L16.300003 227.99998L27.200003 227.99998zM91.2 217.9L94.5 228.09999L105.3 228.09999L96.600006 234.4L99.90001 244.59999L91.20001 238.29999L82.500015 244.59999L85.90002 234.29999L77.20002 227.99998L87.80002 227.99998zM152 217.9L155.3 228.09999L166 228.09999L157.6 234.4L160.90001 244.59999L152.20001 238.29999L143.50002 244.59999L146.80002 234.29999L138.10002 227.99998L148.90002 227.99998zM212.8 217.9L216.1 228.09999L226.90001 228.09999L218.20001 234.4L221.50002 244.59999L212.80002 238.29999L204.10002 244.59999L207.50002 234.29999L198.70001 227.99998L209.40001 227.99998zM273.6 217.9L276.9 228.09999L287.6 228.09999L279 234.4L282.3 244.59999L273.59998 238.29999L264.89996 244.59999L268.19995 234.29999L259.59995 227.99998L270.29996 227.99998zM334.4 217.9L337.69998 228.09999L348.49997 228.09999L339.69998 234.4L343.09998 244.59999L334.39996 238.29999L325.69995 244.59999L329.09995 234.29999L320.29996 227.99998L331.09995 227.99998z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                    </g>
                                                </g>
                                                </svg></span>Usa
									</a>
								</li>
								<li class="col-lg-6 mb-2 mb-2">
									<a href="{{url('lang/th')}}" class="btn btn-country btn-lg btn-block  @if(app()->getLocale() == 'th') active @endif">
										<span class="country-selector m-1"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" width="20" height="15">

                                            <g transform="matrix(0.03125 0 0 0.03125 0 0)">
                                                <g>
                                                    <path d="M0 0L640 0L640 480L0 480L0 0z" stroke="none" fill="#F4F5F8" fill-rule="nonzero"/>
                                                    <path d="M0 162.5L640 162.5L640 322.5L0 322.5L0 162.5z" stroke="none" fill="#2D2A4A" fill-rule="nonzero"/>
                                                    <path d="M0 0L640 0L640 82.5L0 82.5L0 0zM0 400L640 400L640 480L0 480z" stroke="none" fill="#A51931" fill-rule="nonzero"/>
                                                </g>
                                            </g>
                                            </svg></span>Thai
									</a>
								</li>
								<li class="col-lg-6 mb-2">
									<a href="{{url('lang/cn')}}" class="btn btn-country btn-lg btn-block  @if(app()->getLocale() == 'cn') active @endif">
										<span class="country-selector m-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" width="20" height="15">

                                                <g transform="matrix(0.03125 0 0 0.03125 0 0)">
                                                    <path d="M0 0L640 0L640 480L0 480L0 0z" stroke="none" fill="#EE1C25" fill-rule="nonzero"/>
                                                    <path transform="matrix(71.9991 0 0 72 120 120)" d="M-0.6 0.8L0 -1L0.6 0.8L-1 -0.3L1 -0.3L-0.6 0.8z" stroke="none" fill="#FFFF00" fill-rule="nonzero"/>
                                                    <path transform="matrix(-12.33562 -20.5871 20.58684 -12.33577 240.3 48)" d="M-0.6 0.8L0 -1L0.6 0.8L-1 -0.3L1 -0.3L-0.6 0.8z" stroke="none" fill="#FFFF00" fill-rule="nonzero"/>
                                                    <path transform="matrix(-3.38573 -23.75998 23.75968 -3.38578 288 95.8)" d="M-0.6 0.8L0 -1L0.6 0.8L-1 -0.3L1 -0.3L-0.6 0.8z" stroke="none" fill="#FFFF00" fill-rule="nonzero"/>
                                                    <path transform="matrix(6.5991 -23.0749 23.0746 6.59919 288 168)" d="M-0.6 0.8L0 -1L0.6 0.8L-1 -0.3L1 -0.3L-0.6 0.8z" stroke="none" fill="#FFFF00" fill-rule="nonzero"/>
                                                    <path transform="matrix(14.9991 -18.73557 18.73533 14.99929 240 216)" d="M-0.6 0.8L0 -1L0.6 0.8L-1 -0.3L1 -0.3L-0.6 0.8z" stroke="none" fill="#FFFF00" fill-rule="nonzero"/>
                                                </g>
                                                </svg></span>China
									</a>
								</li>
								<li class="col-lg-6 mb-2">
									<a href="javascript:void(0);" class="btn btn-country btn-lg btn-block @if(app()->getLocale() == 'my') active @endif">
										<span class="country-selector m-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" width="20" height="15">

                                                <g transform="matrix(0.03125 0 0 0.03125 0 0)">
                                                    <g>
                                                        <path d="M0 0L640 0L640 480L0 480L0 0z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 0L640 0L640 34.3L0 34.3L0 0z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 34.3L640 34.3L640 68.6L0 68.6L0 34.3z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 68.6L640 68.6L640 102.899994L0 102.899994L0 68.6z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 102.9L640 102.9L640 137L0 137L0 102.9z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 137.1L640 137.1L640 171.40001L0 171.40001L0 137.1z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 171.4L640 171.4L640 205.7L0 205.7L0 171.4z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 205.7L640 205.7L640 240L0 240L0 205.7z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 240L640 240L640 274.3L0 274.3L0 240z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 274.3L640 274.3L640 308.59998L0 308.59998L0 274.3z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 308.6L640 308.6L640 342.9L0 342.9L0 308.6z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 342.9L640 342.9L640 377L0 377L0 342.9z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 377.1L640 377.1L640 411.4L0 411.4L0 377.1z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 411.4L640 411.4L640 445.69998L0 445.69998L0 411.4z" stroke="none" fill="#CC0000" fill-rule="nonzero"/>
                                                        <path d="M0 445.7L640 445.7L640 480L0 480L0 445.7z" stroke="none" fill="#FFFFFF" fill-rule="nonzero"/>
                                                        <path d="M0 0.5L320 0.5L320 274.8L0 274.8L0 0.5z" stroke="none" fill="#000066" fill-rule="nonzero"/>
                                                        <path d="M207.5 73.8L213.5 114.5L236.5 80.5L224.1 119.7L259.6 98.899994L231.5 128.9L272.5 125.7L234.2 140.5L272.5 155.3L231.5 152.1L259.6 182.1L224.1 161.3L236.40001 200.6L213.40001 166.5L207.40001 207.2L201.50002 166.5L178.50002 200.5L190.90001 161.3L155.40001 182.1L183.40001 152.1L142.40001 155.3L180.80002 140.5L142.50002 125.7L183.50002 128.9L155.40001 98.899994L190.90001 119.7L178.50002 80.399994L201.50002 114.49999L207.50002 73.79999L207.5 73.8zM174.2 75.5C 152.19963 65.712494 126.74045 67.72525 106.55132 80.848175C 86.3622 93.9711 74.18696 116.420715 74.2 140.5C 74.18696 164.57928 86.36219 187.0289 106.551315 200.15182C 126.74043 213.27475 152.19963 215.2875 174.2 205.5C 149.8251 223.0092 117.70081 225.40308 91 211.7C 64.330475 197.9785 47.576294 170.49237 47.6 140.5C 47.60991 110.51544 64.38622 83.05391 91.060425 69.35862C 117.734634 55.663338 149.82762 58.033997 174.2 75.5z" stroke="none" fill="#FFCC00" fill-rule="nonzero"/>
                                                    </g>
                                                </g>
                                                </svg></span>Malaysia
									</a>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			<!-- Country-selector modal-->

			<!-- Message Modal -->
			<div class="modal fade" id="chatmodel" tabindex="-1" role="dialog"  aria-hidden="true">
				<div class="modal-dialog modal-dialog-right chatbox" role="document">
					<div class="modal-content chat border-0">
						<div class="card overflow-hidden mb-0 border-0">
							<!-- action-header -->
							<div class="action-header clearfix">
								<div class="float-start hidden-xs d-flex ms-2">
									<div class="img_cont me-3">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img" alt="img">
									</div>
									<div class="align-items-center mt-0">
										<h4 class="text-white mb-0 font-weight-semibold">Daneil Scott</h4>
										<span class="dot-label bg-success"></span><span class="me-3 text-white">online</span>
									</div>
								</div>
								<ul class="ah-actions actions align-items-center">
									<li class="call-icon">
										<a href="" class="d-done d-md-block phone-button" data-bs-toggle="modal" data-bs-target="javascript:void(0);audiomodal">
											<i class="fe fe-phone"></i>
										</a>
									</li>
									<li class="video-icon">
										<a href="" class="d-done d-md-block phone-button" data-bs-toggle="modal" data-bs-target="javascript:void(0);videomodal">
											<i class="fe fe-video"></i>
										</a>
									</li>
									<li class="dropdown">
										<a href="" data-bs-toggle="dropdown" aria-expanded="true">
											<i class="fe fe-more-vertical"></i>
										</a>
										<ul class="dropdown-menu dropdown-menu-right">
											<li><i class="fa fa-user-circle"></i> View profile</li>
											<li><i class="fa fa-users"></i>Add friends</li>
											<li><i class="fa fa-plus"></i> Add to group</li>
											<li><i class="fa fa-ban"></i> Block</li>
									</ul>
									</li>
									<li>
										<a href=""  class="" data-bs-dismiss="modal" aria-label="Close">
											<span aria-hidden="true"><i class="fe fe-x-circle text-white"></i></span>
										</a>
									</li>
							</ul>
							</div>
							<!-- action-header end -->

							<!-- msg_card_body -->
							<div class="card-body msg_card_body">
								<div class="chat-box-single-line">
									<abbr class="timestamp">july 1st, 2021</abbr>
								</div>
								<div class="d-flex justify-content-start">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										Hi, how are you Jenna Side?
										<span class="msg_time">8:40 AM, Today</span>
									</div>
								</div>
								<div class="d-flex justify-content-end ">
									<div class="msg_cotainer_send">
										Hi Connor Paige i am good tnx how about you?
										<span class="msg_time_send">8:55 AM, Today</span>
									</div>
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/9.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
								</div>
								<div class="d-flex justify-content-start ">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										I am good too, thank you for your chat template
										<span class="msg_time">9:00 AM, Today</span>
									</div>
								</div>
								<div class="d-flex justify-content-end ">
									<div class="msg_cotainer_send">
										You welcome Connor Paige
										<span class="msg_time_send">9:05 AM, Today</span>
									</div>
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/9.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
								</div>
								<div class="d-flex justify-content-start ">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										Yo, Can you update Views?
										<span class="msg_time">9:07 AM, Today</span>
									</div>
								</div>
								<div class="d-flex justify-content-end mb-4">
									<div class="msg_cotainer_send">
										But I must explain to you how all this mistaken  born and I will give
										<span class="msg_time_send">9:10 AM, Today</span>
									</div>
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/9.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
								</div>
								<div class="d-flex justify-content-start ">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										Yo, Can you update Views?
										<span class="msg_time">9:07 AM, Today</span>
									</div>
								</div>
								<div class="d-flex justify-content-end mb-4">
									<div class="msg_cotainer_send">
										But I must explain to you how all this mistaken  born and I will give
										<span class="msg_time_send">9:10 AM, Today</span>
									</div>
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/9.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
								</div>
								<div class="d-flex justify-content-start ">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										Yo, Can you update Views?
										<span class="msg_time">9:07 AM, Today</span>
									</div>
								</div>
								<div class="d-flex justify-content-end mb-4">
									<div class="msg_cotainer_send">
										But I must explain to you how all this mistaken  born and I will give
										<span class="msg_time_send">9:10 AM, Today</span>
									</div>
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/9.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
								</div>
								<div class="d-flex justify-content-start">
									<div class="img_cont_msg">
										<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user_img_msg" alt="img">
									</div>
									<div class="msg_cotainer">
										Okay Bye, text you later..
										<span class="msg_time">9:12 AM, Today</span>
									</div>
								</div>
							</div>
							<!-- msg_card_body end -->
							<!-- card-footer -->
							<div class="card-footer">
								<div class="msb-reply d-flex">
									<div class="input-group">
										<input type="text" class="form-control " placeholder="Typing....">
										<div class="input-group-append ">
											<button type="button" class="btn btn-primary ">
												<i class="far fa-paper-plane" aria-hidden="true"></i>
											</button>
										</div>
									</div>
								</div>
							</div><!-- card-footer end -->
						</div>
					</div>
				</div>
			</div>

			<!--Video Modal -->
			<div id="videomodal" class="modal fade">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-body mx-auto text-center p-7">
							<h5>Nowa Video call</h5>
							<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user-img-circle h-8 w-8 mt-4 mb-3" alt="img">
							<h4 class="mb-1 font-weight-semibold">Daneil Scott</h4>
							<h6>Calling...</h6>
							<div class="mt-5">
								<div class="row">
									<div class="col-4">
										<a class="icon icon-shape rounded-circle mb-0 me-3" href="javascript:void(0);">
											<i class="fas fa-video-slash"></i>
										</a>
									</div>
									<div class="col-4">
										<a class="icon icon-shape rounded-circle text-white mb-0 me-3" href="javascript:void(0);" data-bs-dismiss="modal" aria-label="Close">
											<i class="fas fa-phone bg-danger text-white"></i>
										</a>
									</div>
									<div class="col-4">
										<a class="icon icon-shape rounded-circle mb-0 me-3" href="javascript:void(0);">
											<i class="fas fa-microphone-slash"></i>
										</a>
									</div>
								</div>
							</div>
						</div><!-- modal-body -->
					</div>
				</div><!-- modal-dialog -->
			</div><!-- modal -->

			<!-- Audio Modal -->
			<div id="audiomodal" class="modal fade">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-body mx-auto text-center p-7">
							<h5>Nowa Voice call</h5>
							<img src="{{asset('assets/img/faces/6.jpg')}}" class="rounded-circle user-img-circle h-8 w-8 mt-4 mb-3" alt="img">
							<h4 class="mb-1  font-weight-semibold">Daneil Scott</h4>
							<h6>Calling...</h6>
							<div class="mt-5">
								<div class="row">
									<div class="col-4">
										<a class="icon icon-shape rounded-circle mb-0 me-3" href="javascript:void(0);">
											<i class="fas fa-volume-up bg-light"></i>
										</a>
									</div>
									<div class="col-4">
										<a class="icon icon-shape rounded-circle text-white mb-0 me-3" href="javascript:void(0);" data-bs-dismiss="modal" aria-label="Close">
											<i class="fas fa-phone text-white bg-primary"></i>
										</a>
									</div>
									<div class="col-4">
										<a class="icon icon-shape  rounded-circle mb-0 me-3" href="javascript:void(0);">
											<i class="fas fa-microphone-slash bg-light"></i>
										</a>
									</div>
								</div>
							</div>
						</div><!-- modal-body -->
					</div>
				</div><!-- modal-dialog -->
			</div><!-- modal -->
