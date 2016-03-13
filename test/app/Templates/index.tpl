<section class="worklistopt vbox bg-white">
    <section>
        <section data-section="workspace" class="workspace hbox stretch">
            <section>
                <section class="vbox">
                    <section class="workspace-body scrollable treeview w-f-md">
                        
                    </section>
                    <footer class="workspace-footer footer footer-md bg-green" >
                        <section class="workspace-footer-inner">
                            <section class="hbox stretch">
                                <section>
                                    <section class="wrapper task-input-wrapper">
                                        <label class="task-input-label label-title">TASK NAME</label>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input tabindex="2" type="text" placeholder="Type task here..." class="task-input input-sm form-control">
                                                <div class="input-group-btn">
                                                    <a tabindex="4" href="#" class="btn btn-sm no-border btn-add-task"><i class="fa fa-plus"></i></a>
                                                    <a tabindex="5" href="#" class="btn btn-sm no-border btn-add-collapse"><i class="fa fa-chevron-down"></i></a>
                                                </div>
                                                <div class="task-input-loading">
                                                    <div class="spinner-loading">
                                                        <div class="spinner">
                                                            <div class="rect1"></div>
                                                            <div class="rect2"></div>
                                                            <div class="rect3"></div>
                                                            <div class="rect4"></div>
                                                            <div class="rect5"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <label class="task-input-label label-desc">DESCRIPTION</label>
                                        <div class="form-group">
                                            <textarea tabindex="3" class="task-desc form-control input-sm" placeholder="Type description here..."></textarea>
                                        </div>

                                    </section>
                                </section>

                                <aside class="aside-xl bg-dark">
                                    <section class="wrapper task-search-wrapper">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input type="text" placeholder="Search..." class="task-search input-sm form-control input-s-sm bg-empty no-border b-green b-b">
                                                <div class="input-group-btn">
                                                    <a data-toggle="tooltip" data-placement="top" data-original-title="Clear Search" href="#" class="btn btn-sm btn-sm no-border btn-cls-search"><i class="fa fa-times"></i></a>
                                                    <a data-toggle="tooltip" data-placement="top" data-original-title="Advanced Filter"  href="#" class="btn btn-sm btn-sm no-border btn-adv-filter"><i class="fa fa-filter"></i></a>
                                                    <a  data-toggle="tooltip" data-placement="top" data-original-title="Users" href="#" class="btn btn-sm btn-sm no-border btn-show-sidebar"><i class="fa fa-male"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </aside>

                            </section>
                        </section>

                    </footer>
                </section>
            </section>

        </section>

        <section data-section="sidebar" class="worklistopt-sidebar bg-base aside-lg b-l">
            <a class="toggle-handle btn-hide-sidebar bg-base" href="#">
                <i class="ion-chevron-right"></i>
            </a>
            <section class="vbox">
                <section class="scrollable">
                    <section class="wrapper">
                        <section class="panel panel-default">
                            <h4 class="font-thin padder"><i class="ion ion-person-stalker"></i> Workbook Users</h4>
                            <div class="form-group wrapper">
                                <input name="finduser" type="text" class="form-control btn-rounded" placeholder="Search users..." />
                            </div>
                            <ul class="user-list list-group no-radius m-b-none m-t-n-xxs list-group-lg no-border">
                            </ul>
                        </section>

                    </section>

                </section>
            </section>

        </section>

        <section data-section="filter" class="worklistopt-filter panel no-border no-radius bg-light">
            <header class="panel-heading bg-dark">
                <button type="button" class="close">&times;</button>
                <h4 class="panel-title">Advanced Search</h4>
            </header>
            <section class="panel-body">
                <div class="btn-group">
                    <button data-toggle="dropdown" class="btn btn-default btn-info btn-xs btn-add-field dropdown-toggle">
                        <i class="glyphicon glyphicon-plus"></i>
                    </button>

                    <ul class="dropdown-menu">
                        <li><a href="#" data-filter="Title">Task Title (Caption)</a></li>
                        <li><a href="#" data-filter="Users">Users In Charge (PIC)</a></li>
                        <li><a href="#" data-filter="StartDate">Start Date</a></li>
                        <li><a href="#" data-filter="EndDate">End Date</a></li>
                        <li><a href="#" data-filter="Status">Task Status</a></li>
                        <li><a href="#" data-filter="Priority">Task Priority</a></li>
                    </ul>

                </div>

                <a href="#" class="btn btn-default btn-info btn-xs btn-apply-filter">Apply Filter</a>
                <a href="#" class="btn btn-default btn-xs btn-clear-filter">Clear</a>

                <div class="line"></div>

                <table class="filter-table">
                    <thead>
                        <tr>
                            <th>&nbsp;</th>
                            <th>Field</th>
                            <th>Conds</th>
                            <th>Value</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>

                    </tbody>

                </table>
            </section>
        </section>

    </section>

</section>
