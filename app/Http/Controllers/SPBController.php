<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Tax;
use App\Models\Role;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\SpbProject;
use App\Models\ContactType;
use App\Models\DocumentSPB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\LogsSPBProject;
use App\Facades\MessageActeeve;
use App\Models\SpbProjectTermin;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use App\Models\SpbProject_Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductCompanySpbProject;
use App\Http\Requests\SpbProject\AcceptRequest;
use App\Http\Requests\SpbProject\CreateRequest;
use App\Http\Requests\SpbProject\UpdateRequest;
use App\Http\Requests\SpbProject\PaymentRequest;
use App\Http\Requests\SpbProject\AddProdukRequest;
use App\Http\Requests\SpbProject\ActivateSpbRequest;
use App\Http\Requests\SpbProject\RejectProdukRequest;
use App\Http\Requests\SpbProject\UpdateProdukRequest;
use App\Http\Requests\SpbProject\PaymentProdukRequest;
use App\Http\Requests\SpbProject\ActivateProdukRequest;
use App\Http\Requests\SpbProject\PaymentVendorRequest;
use App\Http\Requests\SpbProject\UpdatePaymentFlashInvRequest;
use App\Http\Requests\SpbProject\UpdateTerminRequest;
use App\Http\Resources\SPBproject\SPBprojectCollection;
use App\Http\Resources\SPBproject\SpbProjectPrintCollection;
use App\Models\SpbProject_Category as SpbProjectCategory;

class SPBController extends Controller
{
    public function index(Request $request)
    {
        $query = SpbProject::query();

         if (auth()->user()->role_id == Role::MARKETING) {
            $query->whereHas('project', function ($q) {
                $q->where('user_id', auth()->user()->id) 
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id); 
                  });
            });
        }   

        $query->with([
            'user', 
            'products', 
            'project', 
            'status', 
            'vendors',
            'project.tenagaKerja'  
        ]);

        // 🔹 **Pencarian (SEARCH)**
        if ($request->has('search')) {
            $searchTerm = $request->search;

            $query->where(function ($q) use ($searchTerm) {
                // Pencarian berdasarkan doc_no_spb dan doc_type_spb
                $q->where('doc_no_spb', 'like', '%' . $searchTerm . '%') // Nomor SPB
                ->orWhere('doc_type_spb', 'like', '%' . $searchTerm . '%') // Tipe SPB

                // Pencarian berdasarkan kategori
                ->orWhereHas('category', function ($q) use ($searchTerm) { 
                    $q->where('name', 'like', '%' . $searchTerm . '%'); // Nama kategori SPB
                })

                // Pencarian berdasarkan nama perusahaan langsung di tabel companies
                ->orWhereHas('company', function ($q) use ($searchTerm) { 
                    $q->where('name', 'like', '%' . $searchTerm . '%'); // Nama perusahaan (company name)
                })
                
                // Pencarian berdasarkan nama perusahaan yang terhubung melalui pivot table product_company_spbproject
                ->orWhereHas('productCompanySpbprojects', function ($q) use ($searchTerm) {
                    $q->whereHas('company', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%'); // Nama perusahaan yang terhubung via pivot table
                    });
                });
            });
        }

        /* if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
        
            // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }
        
            // Pastikan format sudah menjadi array dengan dua elemen
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]); // Pastikan tidak ada spasi tambahan
                $endDate = trim($dateRange[1]);
        
                // Gunakan filter tanggal
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($q1) use ($startDate, $endDate) {
                        $q1->where('tab_spb', SpbProject::TAB_PAID)
                           ->whereBetween('updated_at', [$startDate, $endDate]);
                    })
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('tab_spb', '!=', SpbProject::TAB_PAID)
                           ->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
                               $q3->whereBetween('payment_date', [$startDate, $endDate]);
                           });
                    });
                });
            }
        } */

        if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
        
            // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }
        
            // Pastikan format sudah benar
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]);
                $endDate = trim($dateRange[1]);
        
                // Ambil kategori SPB yang dipilih pengguna
                $docTypeSpb = $request->has('doc_type_spb') ? explode(',', strtoupper($request->doc_type_spb)) : [];
                $tabSpb = $request->has('tab_spb') ? (int) $request->tab_spb : null;
        
                // Gunakan filter berdasarkan kondisi Borongan atau Non-Borongan
                $query->where(function ($q) use ($startDate, $endDate, $docTypeSpb, $tabSpb) {
                    
                    
                    if (in_array(strtoupper('BORONGAN'), array_map('strtoupper', $docTypeSpb)) || $tabSpb === SpbProject::TAB_PAYMENT_REQUEST) {
                        $q->whereHas('termins', function ($q1) use ($startDate, $endDate) {
                            $q1->whereBetween('tanggal', [$startDate, $endDate]);
                        });
                    }
                                  
                    // Jika kategori adalah INVOICE atau FLASHCASH dan tab_spb adalah TAB_PAYMENT_REQUEST
                    elseif ((in_array(SpbProject_Category::INVOICE, $docTypeSpb) || in_array(SpbProject_Category::FLASH_CASH, $docTypeSpb)) 
                        && $tabSpb === SpbProject::TAB_PAYMENT_REQUEST) {
                        
                        $q->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
                            $q3->whereBetween('payment_date', [$startDate, $endDate]);
                        });

                        // Tambahkan kondisi jika produk sudah Paid meskipun masih dalam TAB_PAYMENT_REQUEST
                        $q->orWhereHas('productCompanySpbprojects', function ($q4) use ($startDate, $endDate) {
                            $q4->where('status_vendor', ProductCompanySpbProject::TEXT_PAID_PRODUCT)
                            ->whereBetween('payment_date', [$startDate, $endDate]);
                        });

                    } 
                    // Jika kategori lain, gunakan filter default
                    else {
                        $q->where(function ($q1) use ($startDate, $endDate) {
                            // $q1->where('tab_spb', SpbProject::TAB_PAID)
                            // ->whereBetween('updated_at', [$startDate, $endDate]);
                            $q1->where('tab_spb', SpbProject::TAB_PAID)
                                ->whereDate('updated_at', '>=', $startDate)   
                                ->whereDate('updated_at', '<=', $endDate);
                        });
                    }
                });
                // Uncomment untuk debugging langsung di browser
                // dd($query->toSql(), $query->getBindings());
            }
        }

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }  
       

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
    
            // Filter berdasarkan company_id di SPB Project langsung dan melalui pivot table product_company_spbproject
            $query->where(function ($q) use ($vendorId) {
                $q->where('company_id', $vendorId) // Filter berdasarkan company_id di SPB Project
                  ->orWhereHas('productCompanySpbprojects', function ($q) use ($vendorId) {
                      $q->where('company_id', $vendorId); // Filter berdasarkan company_id di pivot table
                  });
            });
        }

        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang);
            $query->whereHas('project.tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds); 
            });
        }

        if ($request->has('supervisor_id')) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->supervisor_id) 
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::SUPERVISOR); 
                  });
            });
        }   

        if ($request->has('owner_id')) {
            $query->whereNotNull('request_owner') // Pastikan request_owner sudah diisi
                  ->where(function ($q) use ($request) {
                      // Filter berdasarkan owner_id yang diberikan dalam request
                      $q->whereHas('user', function ($subQ) use ($request) {
                          $subQ->where('id', $request->owner_id)
                               ->whereHas('role', function ($roleQuery) {
                                   $roleQuery->where('role_id', Role::OWNER);
                               });
                      })->orWhere('request_owner', $request->owner_id);
                  });
        }   
        
        
        if ($request->has('gudang_id')) {
            $query->whereNotNull('know_kepalagudang') // Pastikan hanya data yang memiliki know_kepalagudang
                  ->where(function ($q) use ($request) {
                      // Filter berdasarkan gudang_id yang diberikan dalam request
                      $q->whereHas('user', function ($subQ) use ($request) {
                          $subQ->where('id', $request->gudang_id)
                               ->whereHas('role', function ($roleQuery) {
                                   $roleQuery->where('role_id', Role::GUDANG);
                               });
                      })->orWhere('know_kepalagudang', $request->gudang_id);
                  });
        }
        

        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }

        if ($request->has('doc_type_spb')) {
            $docTypes = explode(',', $request->doc_type_spb); // Pisahkan berdasarkan koma
            $query->where(function($q) use ($docTypes) {
                foreach ($docTypes as $docType) {
                    $q->orWhere('doc_type_spb', 'like', '%' . trim($docType) . '%');
                }
            });
        }        
        

        if ($request->has('status')) {
            $status = $request->status;
            // Pastikan status valid dan cocok dengan ID status
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }

         // Filter berdasarkan type_project (1: Project, 2: Non-Project)
        if ($request->has('type_project')) {
            $typeProject = $request->type_project;
            if (in_array($typeProject, [SpbProject::TYPE_PROJECT_SPB, SpbProject::TYPE_NON_PROJECT_SPB])) {
                $query->where('type_project', $typeProject);
            }
        }

        if ($request->has('status_produk')) {
            $status_produk = $request->status_produk;
        
            $query->whereHas('products', function ($query) use ($status_produk) {
                $query->where('status_produk', $status_produk);
            });
        }        


        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
        
            $query->whereHas('project', function ($q) use ($typeProjects) {
                $q->whereIn('type_projects', $typeProjects); // Filter berdasarkan type_projects di tabel projects
            });
        }        

        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                // Tentukan nama tabel untuk kolom 'id'
                $query->where('projects.id', $request->project);
            });
        }

         // Filter termin Borongan (1: Belum Lunas, 2: Lunas)
         if ($request->has('type_termin_spb')) {
            $typeTerminSpb = $request->type_termin_spb;
            if (in_array($typeTerminSpb, [SpbProject::TYPE_TERMIN_BELUM_LUNAS, SpbProject::TYPE_TERMIN_LUNAS])) {
                $query->where('type_termin_spb', $typeTerminSpb);
            }
        }

        if ($request->has('tanggal_dibuat_spb') && $request->has('tanggal_berahir_spb')) {
            $tanggalDibuatSpb = $request->tanggal_dibuat_spb;
            $tanggalBerahirSpb = $request->tanggal_berahir_spb;
    
            // Memastikan bahwa filter tanggal hanya berlaku pada kategori "Borongan"
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereBetween('tanggal_dibuat_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb])
                  ->orWhereBetween('tanggal_berahir_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb]);
            });
        } elseif ($request->has('tanggal_dibuat_spb')) {
            $query->whereDate('tanggal_dibuat_spb', '=', $request->tanggal_dibuat_spb);
        } elseif ($request->has('tanggal_berahir_spb')) {
            $query->whereDate('tanggal_berahir_spb', '=', $request->tanggal_berahir_spb);
        }

        // Filter produk berdasarkan tanggal 'date' atau 'due_date' pada produk
        if ($request->has('tanggal_date_produk') && $request->has('tanggal_due_date_produk')) {
            $tanggalDateProduk = $request->tanggal_date_produk;
            $tanggalDueDateProduk = $request->tanggal_due_date_produk;
            
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDateProduk, $tanggalDueDateProduk) {
                $q->whereBetween('date', [$tanggalDateProduk, $tanggalDueDateProduk])
                ->orWhereBetween('due_date', [$tanggalDateProduk, $tanggalDueDateProduk]);
            });
        } elseif ($request->has('tanggal_date_produk')) {
            $tanggalDateProduk = $request->tanggal_date_produk;
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDateProduk) {
                $q->whereDate('date', '=', $tanggalDateProduk);
            });
        } elseif ($request->has('tanggal_due_date_produk')) {
            $tanggalDueDateProduk = $request->tanggal_due_date_produk;
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDueDateProduk) {
                $q->whereDate('due_date', '=', $tanggalDueDateProduk);
            });
        }

        // Pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case SpbProject::TAB_SUBMIT:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                case SpbProject::TAB_VERIFIED:
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $query->orderBy('tanggal_berahir_spb', 'asc')->orderBy('doc_no_spb', 'asc');
                    break;
                case SpbProject::TAB_PAID:
                    $query->orderBy('updated_at', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                default:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan tanggal dibuat secara descending
            $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
        }

        // Pagination
        $spbProjects = $query->paginate($request->per_page);

        // Return data dalam bentuk koleksi
        return new SPBprojectCollection($spbProjects);
    } 

    public function indexall(Request $request) {
        $query = SpbProject::query();
    
        // Filter berdasarkan role user
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->whereHas('project', function ($q) {
                $q->where('user_id', auth()->user()->id) 
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id); 
                  });
            });
        }
    
        $query->with([
            'user', 
            'products', 
            'project', 
            'status', 
            'vendors',
            'project.tenagaKerja'  
        ]);
    
        // if ($request->has('date_range')) {
        //     $dateRange = $request->input('date_range');
        
        //     // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
        //     if (is_string($dateRange)) {
        //         $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
        //         $dateRange = explode(',', $dateRange); // Ubah string menjadi array
        //     }
        
        //     // Pastikan format sudah menjadi array dengan dua elemen
        //     if (is_array($dateRange) && count($dateRange) === 2) {
        //         $startDate = trim($dateRange[0]); // Pastikan tidak ada spasi tambahan
        //         $endDate = trim($dateRange[1]);
        
        //         // Gunakan filter tanggal
        //         $query->where(function ($q) use ($startDate, $endDate) {
        //             $q->where(function ($q1) use ($startDate, $endDate) {
        //                 $q1->where('tab_spb', SpbProject::TAB_PAID)
        //                    ->whereBetween('updated_at', [$startDate, $endDate]);
        //             })
        //             ->orWhere(function ($q2) use ($startDate, $endDate) {
        //                 $q2->where('tab_spb', '!=', SpbProject::TAB_PAID)
        //                    ->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
        //                        $q3->whereBetween('payment_date', [$startDate, $endDate]);
        //                    });
        //             });
        //         });
        //     }
        // }


        if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
        
            // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }
        
            // Pastikan format sudah benar
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]);
                $endDate = trim($dateRange[1]);
        
                // Ambil kategori SPB yang dipilih pengguna
                $docTypeSpb = $request->has('doc_type_spb') ? explode(',', strtoupper($request->doc_type_spb)) : [];
                $tabSpb = $request->has('tab_spb') ? (int) $request->tab_spb : null;
        
                // Gunakan filter berdasarkan kondisi Borongan atau Non-Borongan
                $query->where(function ($q) use ($startDate, $endDate, $docTypeSpb, $tabSpb) {
                    
                    // Jika kategori SPB adalah BORONGAN dan tab_spb adalah TAB_PAYMENT_REQUEST
                   /*  if (in_array(SpbProject_Category::BORONGAN, $docTypeSpb) || $tabSpb === SpbProject::TAB_PAYMENT_REQUEST) {
                        $q->whereBetween('updated_at', [$startDate, $endDate]);
                    }  */
                    if (in_array(strtoupper('BORONGAN'), array_map('strtoupper', $docTypeSpb)) || $tabSpb === SpbProject::TAB_PAYMENT_REQUEST) {
                        $q->whereHas('termins', function ($q1) use ($startDate, $endDate) {
                            $q1->whereBetween('tanggal', [$startDate, $endDate]);
                        });
                    }
                                  
                    // Jika kategori adalah INVOICE atau FLASHCASH dan tab_spb adalah TAB_PAYMENT_REQUEST
                    elseif ((in_array(SpbProject_Category::INVOICE, $docTypeSpb) || in_array(SpbProject_Category::FLASH_CASH, $docTypeSpb)) 
                        && $tabSpb === SpbProject::TAB_PAYMENT_REQUEST) {
                        
                        $q->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
                            $q3->whereBetween('payment_date', [$startDate, $endDate]);
                        });

                        // Tambahkan kondisi jika produk sudah Paid meskipun masih dalam TAB_PAYMENT_REQUEST
                        $q->orWhereHas('productCompanySpbprojects', function ($q4) use ($startDate, $endDate) {
                            $q4->where('status_vendor', ProductCompanySpbProject::TEXT_PAID_PRODUCT)
                            ->whereBetween('payment_date', [$startDate, $endDate]);
                        });

                    } 
                    // Jika kategori lain, gunakan filter default
                    else {
                        $q->where(function ($q1) use ($startDate, $endDate) {
                            $q1->where('tab_spb', SpbProject::TAB_PAID)
                            ->whereBetween('updated_at', [$startDate, $endDate]);
                        });
                    }
                });
                // Uncomment untuk debugging langsung di browser
                // dd($query->toSql(), $query->getBindings());
            }
        }
        

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }  
       

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
    
            // Filter berdasarkan company_id di SPB Project langsung dan melalui pivot table product_company_spbproject
            $query->where(function ($q) use ($vendorId) {
                $q->where('company_id', $vendorId) // Filter berdasarkan company_id di SPB Project
                  ->orWhereHas('productCompanySpbprojects', function ($q) use ($vendorId) {
                      $q->where('company_id', $vendorId); // Filter berdasarkan company_id di pivot table
                  });
            });
        }

        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang);
            $query->whereHas('project.tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds); 
            });
        }

        if ($request->has('supervisor_id')) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->supervisor_id) // Filter berdasarkan ID supervisor
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::SUPERVISOR); // Pastikan role adalah Supervisor
                  });
            });
        }   

        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }

        if ($request->has('doc_type_spb')) {
            $docTypes = explode(',', $request->doc_type_spb); // Pisahkan berdasarkan koma
            $query->where(function($q) use ($docTypes) {
                foreach ($docTypes as $docType) {
                    $q->orWhere('doc_type_spb', 'like', '%' . trim($docType) . '%');
                }
            });
        }        
        

        if ($request->has('status')) {
            $status = $request->status;
            // Pastikan status valid dan cocok dengan ID status
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }

         // Filter berdasarkan type_project (1: Project, 2: Non-Project)
        if ($request->has('type_project')) {
            $typeProject = $request->type_project;
            if (in_array($typeProject, [SpbProject::TYPE_PROJECT_SPB, SpbProject::TYPE_NON_PROJECT_SPB])) {
                $query->where('type_project', $typeProject);
            }
        }

        if ($request->has('status_produk')) {
            $status_produk = $request->status_produk;
        
            $query->whereHas('products', function ($query) use ($status_produk) {
                $query->where('status_produk', $status_produk);
            });
        }        


        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
        
            $query->whereHas('project', function ($q) use ($typeProjects) {
                $q->whereIn('type_projects', $typeProjects); // Filter berdasarkan type_projects di tabel projects
            });
        }        

        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                // Tentukan nama tabel untuk kolom 'id'
                $query->where('projects.id', $request->project);
            });
        }

         // Filter termin Borongan (1: Belum Lunas, 2: Lunas)
         if ($request->has('type_termin_spb')) {
            $typeTerminSpb = $request->type_termin_spb;
            if (in_array($typeTerminSpb, [SpbProject::TYPE_TERMIN_BELUM_LUNAS, SpbProject::TYPE_TERMIN_LUNAS])) {
                $query->where('type_termin_spb', $typeTerminSpb);
            }
        }

        if ($request->has('tanggal_dibuat_spb') && $request->has('tanggal_berahir_spb')) {
            $tanggalDibuatSpb = $request->tanggal_dibuat_spb;
            $tanggalBerahirSpb = $request->tanggal_berahir_spb;
    
            // Memastikan bahwa filter tanggal hanya berlaku pada kategori "Borongan"
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereBetween('tanggal_dibuat_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb])
                  ->orWhereBetween('tanggal_berahir_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb]);
            });
        } elseif ($request->has('tanggal_dibuat_spb')) {
            $query->whereDate('tanggal_dibuat_spb', '=', $request->tanggal_dibuat_spb);
        } elseif ($request->has('tanggal_berahir_spb')) {
            $query->whereDate('tanggal_berahir_spb', '=', $request->tanggal_berahir_spb);
        }

        // Filter produk berdasarkan tanggal 'date' atau 'due_date' pada produk
        if ($request->has('tanggal_date_produk') && $request->has('tanggal_due_date_produk')) {
            $tanggalDateProduk = $request->tanggal_date_produk;
            $tanggalDueDateProduk = $request->tanggal_due_date_produk;
            
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDateProduk, $tanggalDueDateProduk) {
                $q->whereBetween('date', [$tanggalDateProduk, $tanggalDueDateProduk])
                ->orWhereBetween('due_date', [$tanggalDateProduk, $tanggalDueDateProduk]);
            });
        } elseif ($request->has('tanggal_date_produk')) {
            $tanggalDateProduk = $request->tanggal_date_produk;
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDateProduk) {
                $q->whereDate('date', '=', $tanggalDateProduk);
            });
        } elseif ($request->has('tanggal_due_date_produk')) {
            $tanggalDueDateProduk = $request->tanggal_due_date_produk;
            $query->whereHas('productCompanySpbprojects', function ($q) use ($tanggalDueDateProduk) {
                $q->whereDate('due_date', '=', $tanggalDueDateProduk);
            });
        }
    
        // Pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case SpbProject::TAB_SUBMIT:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                case SpbProject::TAB_VERIFIED:
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $query->orderBy('tanggal_berahir_spb', 'asc')->orderBy('doc_no_spb', 'asc');
                    break;
                case SpbProject::TAB_PAID:
                    $query->orderBy('updated_at', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                default:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan tanggal dibuat secara descending
            $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
        }
    
        // Ambil semua data tanpa pagination
        $spbProjects = $query->get();
    
        // Return data dalam bentuk koleksi
        return new SpbProjectPrintCollection($spbProjects);
    }
    

    public function countingspbusers(Request $request)
    {
        $user = auth()->user();
        $role = $user->role_id;
    
        // Query utama untuk SPB Project
        $query = SpbProject::query();
    
        // Filter berdasarkan kategori
        $query->whereHas('category', function ($q) {
            $q->where('spbproject_category_id', '!=', SpbProject_Category::FLASH_CASH);
        });
    
        // Filter berdasarkan role jika supervisor
        if ($role == Role::SUPERVISOR) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
    
            // Filter berdasarkan company_id di SPB Project langsung dan melalui pivot table product_company_spbproject
            $query->where(function ($q) use ($vendorId) {
                $q->where('company_id', $vendorId) // Filter berdasarkan company_id di SPB Project
                  ->orWhereHas('productCompanySpbprojects', function ($q) use ($vendorId) {
                      $q->where('company_id', $vendorId); // Filter berdasarkan company_id di pivot table
                  });
            });
        }
    
        // Filter berdasarkan tanggal jika ada
        if ($request->has('tanggal_dibuat_spb') || $request->has('tanggal_berahir_spb')) {
            $query->where(function ($q) use ($request) {
                if ($request->has('tanggal_dibuat_spb')) {
                    $tanggalDibuatSpb = Carbon::parse($request->tanggal_dibuat_spb);
                    $q->whereDate('tanggal_dibuat_spb', $tanggalDibuatSpb);
                }
                if ($request->has('tanggal_berahir_spb')) {
                    $tanggalBerahirSpb = Carbon::parse($request->tanggal_berahir_spb);
                    $q->whereDate('tanggal_berahir_spb', $tanggalBerahirSpb);
                }
            });
        }

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }    
    
        if ($request->has('project')) {
            $query->where('project_id', $request->project);
        }
    
        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
    
        // Clone query sebelum pagination diterapkan
        $unapprovedQuery = clone $query;
    
        // Perhitungan sesuai dengan role
        switch ($role) {
            case Role::GUDANG:
                $unapprovedSpb = $query
                    ->whereNotIn('spbproject_category_id', [
                        SpbProject_Category::FLASH_CASH,
                        SpbProject_Category::BORONGAN,
                    ])
                    ->whereNull('know_kepalagudang')
                    ->count();
                break;
        
            case Role::SUPERVISOR:
                $unapprovedSpb = $query
                    ->whereNotIn('spbproject_category_id', [
                        SpbProject_Category::FLASH_CASH,
                        SpbProject_Category::BORONGAN,
                    ])
                    ->whereNull('know_supervisor')
                    ->count();
                break;
        
            case Role::OWNER:
                $unapprovedSpb = $query
                    ->whereNull('request_owner')
                    ->count();
                break;
        
            case Role::ADMIN:
                $unapprovedSpb = $query->count(); // Admin bisa melihat semuanya
                break;
        
            default:
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not authorized for this action.'
                ], 403);
        }
    
        // Pagination setup (Pagination hanya untuk tampilan data, bukan perhitungan)
        $perPage = $request->get('per_page', 10);
        $currentPage = $request->get('page', 1);
        $paginatedData = $query->paginate($perPage, ['*'], 'page', $currentPage);
    
        // Hitung jumlah SPB unapproved berdasarkan role tertentu, tanpa pagination
        if ($role == Role::ADMIN || $role == Role::OWNER) {
            $knowKepalagudangUnapproved = (clone $unapprovedQuery)->whereNull('know_kepalagudang')->count();
            $knowSupervisorUnapproved = (clone $unapprovedQuery)->whereNull('know_supervisor')->count();
            $requestOwnerUnapproved = (clone $unapprovedQuery)->whereNull('request_owner')->count();
        } else {
            // Jika bukan ADMIN atau OWNER, data diatur ke null atau tidak terlihat
            $knowKepalagudangUnapproved = null;
            $knowSupervisorUnapproved = null;
            $requestOwnerUnapproved = null;
        }
    
        // Hitung jumlah total SPB sesuai dengan filter yang diterapkan
        $totalSpb = $request->hasAny(['tanggal_dibuat_spb', 'tanggal_berahir_spb', 'project', 'doc_no_spb'])
            ? $unapprovedQuery->count() 
            : SpbProject::count();
    
        // Nama tab untuk SPB
        $tabNames = [
            SpbProject::TAB_SUBMIT => 'Submit',
            SpbProject::TAB_VERIFIED => 'Verified',
            SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
            SpbProject::TAB_PAID => 'Paid',
        ];
    
        // Detail SPB yang belum disetujui (sesuai dengan pagination)
        $detailUnapprovedSpb = $paginatedData->map(function ($spb) use ($tabNames) {
            return [
                'docNo' => $spb->doc_no_spb,
                'tabSpb' => isset($tabNames[$spb->tab_spb]) ? $tabNames[$spb->tab_spb] : 'Unknown', 
                'projectId' => $spb->project ? $spb->project->id : null,
                'unapprove_spb' => 1,
                'createdAt' => $spb->created_at,
                'updatedAt' => $spb->updated_at,
            ];
        });
    
        // Mengembalikan response JSON
        return response()->json([
            'total_spb' => $totalSpb, 
            'unapprove_spb_total' => $unapprovedSpb,  // Tidak terpengaruh pagination
            'detail_unapprove_spb' => $detailUnapprovedSpb,
            'know_kepalagudang_spb_unapproved' => $knowKepalagudangUnapproved, 
            'know_supervisor_spb_unapproved' => $knowSupervisorUnapproved,
            'request_owner_spb_unapproved' => $requestOwnerUnapproved,
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage(),
            ],
        ]);
    }    

    public function countingspb(Request $request)
    {
        
        // Ambil data kategori Borongan saja
        $query = SpbProject::where('spbproject_category_id', SpbProject_Category::BORONGAN);

        if (auth()->user()->role_id == Role::MARKETING) {
            $query->whereHas('project', function ($q) {
                $q->where('user_id', auth()->user()->id)
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id);
                  });
            });
        }

         /* if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');

            // Jika dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }

            // Pastikan format sudah benar
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]);
                $endDate = trim($dateRange[1]);

                // Gunakan updated_at sebagai filter utama
                $query->whereBetween('updated_at', [$startDate, $endDate]);
            }
        } */

        $startDate = null;
        $endDate = null;
    
        if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
    
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); 
                $dateRange = explode(',', $dateRange);
            }
    
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = Carbon::parse(trim($dateRange[0]))->format('Y-m-d');
                $endDate = Carbon::parse(trim($dateRange[1]))->format('Y-m-d');
    
                // Filter hanya menampilkan termin dalam rentang tanggal
                $query->whereHas('termins', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('tanggal', [$startDate, $endDate]);
                });
            }
        }

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
            $query->where('company_id', $vendorId); 
        }

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }

        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang); 
            $query->whereHas('project.tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds);
            });
        }

        if ($request->has('supervisor_id')) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->supervisor_id) 
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::SUPERVISOR); 
                  });
            });
        }  

        if ($request->has('owner_id')) {
            $query->whereNotNull('request_owner') // Pastikan request_owner sudah diisi
                  ->where(function ($q) use ($request) {
                      // Filter berdasarkan owner_id yang diberikan dalam request
                      $q->whereHas('user', function ($subQ) use ($request) {
                          $subQ->where('id', $request->owner_id)
                               ->whereHas('role', function ($roleQuery) {
                                   $roleQuery->where('role_id', Role::OWNER);
                               });
                      })->orWhere('request_owner', $request->owner_id);
                  });
        }        

        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }

        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }

        if ($request->has('project')) {
            $query->where('project_id', $request->project);
        }

        if ($request->has('doc_type_spb')) {
            $docTypes = explode(',', $request->doc_type_spb); // Pisahkan berdasarkan koma
            $query->where(function($q) use ($docTypes) {
                foreach ($docTypes as $docType) {
                    $q->orWhere('doc_type_spb', 'like', '%' . trim($docType) . '%');
                }
            });
        }       

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
        
            $query->whereHas('project', function ($q) use ($typeProjects) {
                $q->whereIn('type_projects', $typeProjects);
            });
        }   


          // Tambahkan filter berdasarkan proyek jika ada
        if ($request->has('project')) {
            $query->where('project_id', $request->project);
        }

        if ($request->has('tanggal_dibuat_spb') && $request->has('tanggal_berahir_spb')) {
            $tanggalDibuatSpb = $request->tanggal_dibuat_spb;
            $tanggalBerahirSpb = $request->tanggal_berahir_spb;
    
            // Memastikan bahwa filter tanggal hanya berlaku pada kategori "Borongan"
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereBetween('tanggal_dibuat_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb])
                  ->orWhereBetween('tanggal_berahir_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb]);
            });
        } elseif ($request->has('tanggal_dibuat_spb')) {
            $query->whereDate('tanggal_dibuat_spb', '=', $request->tanggal_dibuat_spb);
        } elseif ($request->has('tanggal_berahir_spb')) {
            $query->whereDate('tanggal_berahir_spb', '=', $request->tanggal_berahir_spb);
        }

        $receivedTotalSpbBorongan = $query->count();

        // Subtotal untuk Borongan
        $subtotalHargaTotalBorongan = $query->sum('harga_total_pembayaran_borongan_spb');

        if (!is_null($startDate) && !is_null($endDate)) {
            $subtotalHargaTerminBorongan = SpbProjectTermin::whereHas('spbProject', function ($q) use ($query) {
                    $q->whereIn('doc_no_spb', $query->pluck('doc_no_spb')->toArray());
                })
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->sum('harga_termin');
        } else {
            $subtotalHargaTerminBorongan = $query->sum('harga_termin_spb');
        }


       /*  $subtotalHargaTerminBorongan = 0;

        if (isset($startDate) && isset($endDate)) {
            $subtotalHargaTerminBorongan = SpbProjectTermin::whereHas('spbProject', function ($q) use ($query) {
                    $q->whereIn('doc_no_spb', $query->pluck('doc_no_spb')->toArray());
                })
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->sum('harga_termin');
        } */

        // Inisialisasi variabel untuk menghitung masing-masing status
        $submit = 0;
        $verified = 0;
        $over_due = 0;
        $open = 0;
        $due_date = 0;
        $payment_request_hargatotalborongaspb = 0;
        $paid_request_hargatotalborongaspb = 0;
        $payment_request = 0;
        $paid = 0;

        $nowDate = Carbon::now();

        // Iterasi data Borongan untuk menghitung jumlah per status
        $query->each(function ($spbProject) use (
            &$submit,
            &$verified,
            &$over_due,
            &$open,
            &$due_date,
            &$payment_request_hargatotalborongaspb,
            &$paid_request_hargatotalborongaspb,
            &$payment_request,
            &$paid,
            $nowDate,
            $startDate,
            $endDate
        ) {
            $hargaTotal = $spbProject->harga_total_pembayaran_borongan_spb ?? 0;
            // $hargaTotalTermin = $spbProject->harga_termin_spb ?? 0;

            try {
                $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            } catch (\Exception $e) {
                $dueDate = $nowDate->copy();
            }

            $hargaTotalTerminFiltered = (!is_null($startDate) && !is_null($endDate)) 
            ? $spbProject->termins()->whereBetween('tanggal', [$startDate, $endDate])->sum('harga_termin')
            : $spbProject->harga_termin_spb ?? 0;

            // Hitung berdasarkan status tab
            switch ($spbProject->tab_spb) {
                case SpbProject::TAB_SUBMIT:
                    $submit += $hargaTotal;
                    break;

                case SpbProject::TAB_VERIFIED:
                    $verified += $hargaTotal;
                    break;

                case SpbProject::TAB_PAYMENT_REQUEST:
                    $payment_request_hargatotalborongaspb += $hargaTotal;
                    // $payment_request += $spbProject->harga_termin_spb ?? 0;
                    $payment_request += $hargaTotalTerminFiltered;
                    break;

                case SpbProject::TAB_PAID:
                    $paid_request_hargatotalborongaspb += $hargaTotal;
                    // $paid += $hargaTotalTermin;
                    $paid += $hargaTotalTerminFiltered;
                    break;

                default:
                    break;
            }

            // Tambahkan logika tambahan berdasarkan status SPB
            if ($spbProject->status) {
                switch ($spbProject->status->id) {
                    case SpbProject_Status::OPEN:
                        $open += $hargaTotal;
                        $statusText = SpbProject_Status::TEXT_OPEN;
                        break;
            
                    case SpbProject_Status::OVERDUE:
                        $over_due += $hargaTotal;
                        $statusText = SpbProject_Status::TEXT_OVERDUE;
                        break;
            
                    case SpbProject_Status::DUEDATE:
                        $due_date += $hargaTotal;
                        $statusText = SpbProject_Status::TEXT_DUEDATE;
                        break;
            
                    case SpbProject_Status::VERIFIED:
                        $verified += $hargaTotal;
                        $statusText = SpbProject_Status::TEXT_VERIFIED;
                        break;
            
                    case SpbProject_Status::PAID:
                        $paid += $hargaTotal;
                        $statusText = SpbProject_Status::TEXT_PAID;
                        break;
            
                    default:
                        $statusText = 'Unknown';
                        break;
                }
            }
        });

        $unapprovedSpbBorongan = (clone $query)
            ->whereNull('request_owner')
            ->count();

        $receivedTotalSpbBorongan = $query->count();

        // $unpaidSpbBorongan = $open + $over_due + $due_date;
        $unpaidSpbBorongan = $subtotalHargaTotalBorongan - $subtotalHargaTerminBorongan;

        // Respons JSON
        return response()->json([
            "received_total_spb_borongan" => $receivedTotalSpbBorongan,
            "unpaid_spb_borongan" => $unpaidSpbBorongan, 
            "unapproved_spb_borongan" => $unapprovedSpbBorongan, 
            "subtotal_harga_pembayaran_borongan_spb" => $subtotalHargaTotalBorongan,
            "subtotal_harga_termin_spb" => $subtotalHargaTerminBorongan,
            "submit_harga_total_pembayaran_borongan_spb" => $submit,
            "verified_harga_total_pembayaran_borongan_spb" => $verified,
            "over_due_harga_total_pembayaran_borongan_spb" => $over_due,
            "open_harga_total_pembayaran_borongan_spb" => $open,
            "due_date_harga_total_pembayaran_borongan_spb" => $due_date,
            "payment_request_harga_total_pembayaran_borongan_spb" => $payment_request_hargatotalborongaspb,
            "paid_harga_total_pembayaran_borongan_spb" => $paid_request_hargatotalborongaspb,
            "payment_request_subtotal_harga_termin_spb" => $payment_request,
            "paid_subtotal_harga_termin_spb" => $paid,
        ]);
    }

    public function countingspbnonprojects(Request $request)
    {
        // Query dasar untuk mengambil SPB dengan tipe Non-Project
        $query = SpbProject::where('type_project', SpbProject::TYPE_NON_PROJECT_SPB);
    
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->whereHas('project', function ($q) {
                $q->where('user_id', auth()->user()->id)
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id);
                  });
            });
        }

        // Filter berdasarkan project ID jika ada
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                $query->where('projects.id', $request->project);
            });
        }

        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang); // Mengambil ID tukang dari parameter yang dipisah dengan koma
            $query->whereHas('project.tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds); // Pastikan menggunakan 'users.id'
            });
        }

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
    
            // Filter berdasarkan company_id di SPB Project langsung dan melalui pivot table product_company_spbproject
            $query->where(function ($q) use ($vendorId) {
                $q->where('company_id', $vendorId) // Filter berdasarkan company_id di SPB Project
                  ->orWhereHas('productCompanySpbprojects', function ($q) use ($vendorId) {
                      $q->where('company_id', $vendorId); // Filter berdasarkan company_id di pivot table
                  });
            });
        }

        if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
        
            // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }
        
            // Pastikan format sudah menjadi array dengan dua elemen
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]); // Pastikan tidak ada spasi tambahan
                $endDate = trim($dateRange[1]);
        
                // Gunakan filter tanggal
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($q1) use ($startDate, $endDate) {
                        // $q1->where('tab_spb', SpbProject::TAB_PAID)
                        //    ->whereBetween('updated_at', [$startDate, $endDate]);
                        $q1->where('tab_spb', SpbProject::TAB_PAID)
                            ->whereDate('updated_at', '>=', $startDate)
                            ->whereDate('updated_at', '<=', $endDate);
                    })
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('tab_spb', '!=', SpbProject::TAB_PAID)
                           ->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
                               $q3->whereBetween('payment_date', [$startDate, $endDate]);
                           });
                    });
                });
            }
        }

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }

        if ($request->has('supervisor_id')) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->supervisor_id) // Filter berdasarkan ID supervisor
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::SUPERVISOR); // Pastikan role adalah Supervisor
                  });
            });
        }  

        // Filter berdasarkan tanggal dibuat
        if ($request->has('tanggal_dibuat_spb') && $request->has('tanggal_berahir_spb')) {
            $tanggalDibuatSpb = $request->tanggal_dibuat_spb;
            $tanggalBerahirSpb = $request->tanggal_berahir_spb;
    
            // Memastikan bahwa filter tanggal hanya berlaku pada kategori "Borongan"
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereBetween('tanggal_dibuat_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb])
                  ->orWhereBetween('tanggal_berahir_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb]);
            });
        } elseif ($request->has('tanggal_dibuat_spb')) {
            $query->whereDate('tanggal_dibuat_spb', '=', $request->tanggal_dibuat_spb);
        } elseif ($request->has('tanggal_berahir_spb')) {
            $query->whereDate('tanggal_berahir_spb', '=', $request->tanggal_berahir_spb);
        }

    
        if ($request->has('status')) {
            $status = $request->status;
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }
    
        if ($request->has('status_produk')) {
            $status_produk = $request->status_produk;
    
            $query->whereHas('products', function ($query) use ($status_produk) {
                $query->where('status_produk', $status_produk);
            });
        }
    
        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }
    
        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
    
        // Ambil semua data tanpa pagination
        $collection = $query->get();
    
        // Inisialisasi variabel untuk menghitung masing-masing status
        $submit = 0;
        $verified = 0;
        $over_due = 0;
        $open = 0;
        $due_date = 0;
        $payment_request = 0;
        $paid = 0;
        $totalTerbayarkannon = 0;
    
        // Menghitung jumlah data SPB yang sesuai
        $received = $collection->count();
    
        foreach ($collection as $spbProject) {
            $total = $spbProject->getTotalProdukAttribute(); // Mengambil nilai total dari setiap objek SPB
    
            $totalTerbayarkannon += $spbProject->totalTerbayarProductVendor();

            // Tambahkan logika tambahan berdasarkan status SPB
            if ($spbProject->status) {
                switch ($spbProject->status->id) {
                    case SpbProject_Status::OPEN:
                        $open += $total;
                        break;
    
                    case SpbProject_Status::OVERDUE:
                        $over_due += $total;
                        break;
    
                    case SpbProject_Status::DUEDATE:
                        $due_date += $total;
                        break;
    
                    case SpbProject_Status::VERIFIED:
                        $verified += $total;
                        break;
    
                    case SpbProject_Status::PAID:
                        $paid += $total;
                        break;
    
                    default:
                        break;
                }
            }
    
            switch ($spbProject->tab_spb) {
                case SpbProject::TAB_VERIFIED:
                    $verified += $total;
                    break;
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $payment_request += $total;
                    break;
                case SpbProject::TAB_PAID:
                    $paid += $total;
                    break;
                case SpbProject::TAB_SUBMIT:
                    $submit += $total;
                    break;
            }
        }
    
        $unknownSpb = (clone $query)
            ->where(function ($q) {
                $q->whereNull('know_supervisor')
                ->orWhereNull('know_kepalagudang')
                ->orWhereNull('request_owner');
            })
            ->count();

        $totalproduknon = $payment_request + $paid;
        $unpaidspbnon = $totalproduknon - $paid;
    
        // Respons JSON
        return response()->json([
            'received' => $received, // Jumlah data
            'total_spb_yang_belum_diapprove' => $unknownSpb,
            'total_produk_non' => $totalproduknon,
            "unpaid_spb_nonproject" => $unpaidspbnon, 
            'total_terbayarkan_nonproject' => $totalTerbayarkannon,
            // 'submit' => $submit,
            // 'verified' => $verified,
            'over_due' => $over_due,
            'open' => $open,
            'due_date' => $due_date,
            'payment_request' => $payment_request,
            'paid' => $paid,
        ]);
    }
    
    
    public function counting(Request $request)
    {
        $userId = auth()->id();
        $role = auth()->user()->role_id;
    
        // Ambil semua data SPB (filter default untuk type_project = TYPE_PROJECT_SPB)
       /*  $query = SpbProject::where('type_project', SpbProject::TYPE_PROJECT_SPB)
        ->where('spbproject_category_id', SpbProject_Category::INVOICE);     */
        $query = SpbProject::where('type_project', SpbProject::TYPE_PROJECT_SPB)
        ->whereIn('spbproject_category_id', [SpbProject_Category::FLASH_CASH, SpbProject_Category::INVOICE]);

    
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->whereHas('project', function ($q) {
                $q->where('user_id', auth()->user()->id)
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id);
                  });
            });
        }

        // Filter berdasarkan type_project
        if ($request->has('type_project')) {
            $typeProject = $request->type_project;
            if (in_array($typeProject, [SpbProject::TYPE_PROJECT_SPB, SpbProject::TYPE_NON_PROJECT_SPB])) {
                $query->where('type_project', $typeProject);
            }
        }

        if ($request->has('vendor_id')) {
            $vendorId = $request->vendor_id;
    
            // Fokus filter berdasarkan company_id di pivot table product_company_spbprojects
            $query->whereHas('productCompanySpbprojects', function ($q) use ($vendorId) {
                $q->where('company_id', $vendorId); // Filter berdasarkan company_id di pivot table
            });
        }

        if ($request->has('created_by')) {
            $query->where('user_id', $request->created_by);
        }

        if ($request->has('doc_type_spb')) {
            $docTypes = explode(',', $request->doc_type_spb); // Pisahkan berdasarkan koma
            $query->where(function($q) use ($docTypes) {
                foreach ($docTypes as $docType) {
                    $q->orWhere('doc_type_spb', 'like', '%' . trim($docType) . '%');
                }
            });
        }     

        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang); // Mengambil ID tukang dari parameter yang dipisah dengan koma
            $query->whereHas('project.tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds); // Pastikan menggunakan 'users.id'
            });
        }

        if ($request->has('supervisor_id')) {
            $query->whereHas('project.tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->supervisor_id) // Filter berdasarkan ID supervisor
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::SUPERVISOR); // Pastikan role adalah Supervisor
                  });
            });
        }  

        if ($request->has('owner_id')) {
            $query->whereNotNull('request_owner') // Pastikan request_owner sudah diisi
                  ->where(function ($q) use ($request) {
                      // Filter berdasarkan owner_id yang diberikan dalam request
                      $q->whereHas('user', function ($subQ) use ($request) {
                          $subQ->where('id', $request->owner_id)
                               ->whereHas('role', function ($roleQuery) {
                                   $roleQuery->where('role_id', Role::OWNER);
                               });
                      })->orWhere('request_owner', $request->owner_id);
                  });
        }   
        
        
        if ($request->has('gudang_id')) {
            $query->whereNotNull('know_kepalagudang') // Pastikan hanya data yang memiliki know_kepalagudang
                  ->where(function ($q) use ($request) {
                      // Filter berdasarkan gudang_id yang diberikan dalam request
                      $q->whereHas('user', function ($subQ) use ($request) {
                          $subQ->where('id', $request->gudang_id)
                               ->whereHas('role', function ($roleQuery) {
                                   $roleQuery->where('role_id', Role::GUDANG);
                               });
                      })->orWhere('know_kepalagudang', $request->gudang_id);
                  });
        }

        if ($request->has('date_range')) {
            $dateRange = $request->input('date_range');
        
            // Jika dikirim dalam format string "[2025-01-01, 2025-01-31]", ubah menjadi array
            if (is_string($dateRange)) {
                $dateRange = str_replace(['[', ']'], '', $dateRange); // Hilangkan tanda kurung
                $dateRange = explode(',', $dateRange); // Ubah string menjadi array
            }
        
            // Pastikan format sudah menjadi array dengan dua elemen
            if (is_array($dateRange) && count($dateRange) === 2) {
                $startDate = trim($dateRange[0]); // Pastikan tidak ada spasi tambahan
                $endDate = trim($dateRange[1]);
        
                // Gunakan filter tanggal
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($q1) use ($startDate, $endDate) {
                        // $q1->where('tab_spb', SpbProject::TAB_PAID)
                        //    ->whereBetween('updated_at', [$startDate, $endDate]);
                        $q1->where('tab_spb', SpbProject::TAB_PAID)
                            ->whereDate('updated_at', '>=', $startDate)
                            ->whereDate('updated_at', '<=', $endDate);
                    })
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('tab_spb', '!=', SpbProject::TAB_PAID)
                           ->whereHas('productCompanySpbprojects', function ($q3) use ($startDate, $endDate) {
                               $q3->whereBetween('payment_date', [$startDate, $endDate]);
                           });
                    });
                });
            }
        }

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
        
            $query->whereHas('project', function ($q) use ($typeProjects) {
                $q->whereIn('type_projects', $typeProjects);
            });
        }   
    
        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                $query->where('projects.id', $request->project);
            });
        }
    
        // Filter berdasarkan status SPB
        if ($request->has('status')) {
            $status = $request->status;
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }
    
        // Filter berdasarkan status_produk
        if ($request->has('status_produk')) {
            $status_produk = $request->status_produk;
            $query->whereHas('products', function ($query) use ($status_produk) {
                $query->where('status_produk', $status_produk);
            });
        }
    
        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }
    
        // Filter berdasarkan doc_no_spb
        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
    
        // Filter berdasarkan tanggal_dibuat_spb
       /*  if ($request->has('tanggal_dibuat_spb')) {
            $tanggalDibuatSpb = Carbon::parse($request->input('tanggal_dibuat_spb'));
            $query->whereDate('tanggal_dibuat_spb', $tanggalDibuatSpb);
        }

        // Filter berdasarkan tanggal berakhir, tetap menampilkan data dari tanggal dibuat
        if ($request->filled('tanggal_berahir_spb')) {
            $tanggalBerahirSpb = Carbon::parse($request->input('tanggal_berahir_spb'));
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereDate('tanggal_dibuat_spb', $tanggalDibuatSpb)
                ->orWhereDate('tanggal_berahir_spb', $tanggalBerahirSpb);
            });
        }   */

        if ($request->has('tanggal_dibuat_spb') && $request->has('tanggal_berahir_spb')) {
            $tanggalDibuatSpb = $request->tanggal_dibuat_spb;
            $tanggalBerahirSpb = $request->tanggal_berahir_spb;
    
            // Memastikan bahwa filter tanggal hanya berlaku pada kategori "Borongan"
            $query->where(function ($q) use ($tanggalDibuatSpb, $tanggalBerahirSpb) {
                $q->whereBetween('tanggal_dibuat_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb])
                  ->orWhereBetween('tanggal_berahir_spb', [$tanggalDibuatSpb, $tanggalBerahirSpb]);
            });
        } elseif ($request->has('tanggal_dibuat_spb')) {
            $query->whereDate('tanggal_dibuat_spb', '=', $request->tanggal_dibuat_spb);
        } elseif ($request->has('tanggal_berahir_spb')) {
            $query->whereDate('tanggal_berahir_spb', '=', $request->tanggal_berahir_spb);
        }
    
        // Paginate atau get data
        if ($request->has('page') || $request->has('per_page')) {
            $perPage = (int)$request->per_page ?: 10;
            $spbProjects = $query->paginate($perPage);
            $collection = collect($spbProjects->items());
        } else {
            $collection = $query->get();
        }
    
        // Inisialisasi variabel untuk menghitung masing-masing status
        $submit = 0;
        $verified = 0;
        $over_due = 0;
        $open = 0;
        $due_date = 0;
        $payment_request = 0;
        $paid = 0;
        $totalTerbayarkan = 0;
    
        // Menghitung jumlah data SPB yang sesuai
        $received = $collection->count();
    
        foreach ($collection as $spbProject) {
            $total = $spbProject->getTotalProdukAttribute(); // Mengambil nilai total dari setiap SPB
            $dueDate = Carbon::parse($spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            // $totalTerbayarkan += $spbProject->totalTerbayarProductVendor();
            // if ($spbProject->tab_spb === SpbProject::TAB_PAYMENT_REQUEST) {
            //     $totalTerbayarkan += $spbProject->totalTerbayarProductVendor();
            // }

            // if ($spbProject->tab_spb != SpbProject::TAB_PAID) {
            //     $totalTerbayarkan += $spbProject->totalTerbayarProductVendor();
            // }      
            
            $totalTerbayarkanPerSPB = 0;

            // ✅ Jika berada di TAB_PAYMENT_REQUEST, tambahkan pembayaran yang masih dalam proses + yang sudah dibayar
            if ($spbProject->tab_spb === SpbProject::TAB_PAYMENT_REQUEST) {
                $totalTerbayarkanPerSPB = $spbProject->totalTerbayarProductVendor();
            }

            // ✅ Jika berada di TAB_PAID, ambil total keseluruhan tanpa menjumlahkan ulang
            if ($spbProject->tab_spb === SpbProject::TAB_PAID) {
                $totalTerbayarkan = $spbProject->totalTerbayarProductVendor(); // Total final jika sudah lunas
            } else {
                // Jika belum lunas, tambahkan hanya yang masih dalam proses pembayaran
                $totalTerbayarkan += $totalTerbayarkanPerSPB;
            }
    
            // Logika tambahan berdasarkan status SPB
            if ($spbProject->status) {
                switch ($spbProject->status->id) {
                    case SpbProject_Status::OPEN:
                        $open += $total;
                        break;
    
                    case SpbProject_Status::OVERDUE:
                        $over_due += $total;
                        break;
    
                    case SpbProject_Status::DUEDATE:
                        $due_date += $total;
                        break;
    
                    case SpbProject_Status::VERIFIED:
                        $verified += $total;
                        break;
    
                    case SpbProject_Status::PAID:
                        // $paid += $total;
                        if ($spbProject->tab_spb === SpbProject::TAB_PAID) {
                            $paid += $total;
                        }
                        break;
    
                    default:
                        break;
                }
            }
    
            // Logika tambahan berdasarkan tab_spb
            switch ($spbProject->tab_spb) {
                case SpbProject::TAB_VERIFIED:
                    $verified += $total;
                    if ($dueDate->gt($nowDate)) {
                        $open += $total;
                    } elseif ($dueDate->eq($nowDate)) {
                        $due_date += $total;
                    } elseif ($dueDate->lt($nowDate)) {
                        $over_due += $total;
                    }
                    break;
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $payment_request += $total;
                    if ($dueDate->lt($nowDate)) {
                        $over_due += $total;
                    }
                    break;
                case SpbProject::TAB_PAID:
                    $paid += $total;
                    break;
                case SpbProject::TAB_SUBMIT:
                    $submit += $total;
                    break;
            }
    
            if ($dueDate->eq($nowDate)) {
                $due_date += $total;
            }
        }
    
        $unknownSpb = (clone $query)
        ->where(function ($q) {
            $q->where('spbproject_category_id', SpbProject_Category::INVOICE)  
              ->where(function ($q2) {
                  $q2->whereNull('know_supervisor')
                      ->orWhereNull('know_kepalagudang')
                      ->orWhereNull('request_owner');
              });
        })
        ->count();

        $totalproduk = $submit + $verified + $payment_request + $paid;
        $unpaidspbproject = $totalproduk  - $paid;
        $totalterbayarkaproduk = $paid + $totalTerbayarkan;
    
        // Respons JSON
        return response()->json([
            'received' => $received, // Jumlah data (per halaman atau semua)
            'total_spb_yang_belum_diapprove' => $unknownSpb,
            'total_produk_project_aktif' => $totalproduk,
            "unpaid_spb_project" => $unpaidspbproject, 
            'total_terbayarkan' => $totalTerbayarkan,
            'total_terbayarkan_produk_tabpaid_paymentvendor' => $totalterbayarkaproduk,
            'submit' => $submit,
            'verified' => $verified,
            'over_due' => $over_due,
            'open' => $open,
            'due_date' => $due_date,
            'payment_request' => $payment_request,
            'paid' => $paid,
        ]);
    }
    

    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Validasi kategori SPB
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukan.");
            }

            // Validasi project jika type_project adalah TYPE_PROJECT_SPB
            if ($request->type_project == SpbProject::TYPE_PROJECT_SPB) {
                $project = Project::find($request->project_id);
                if (!$project) {
                    throw new \Exception("Project dengan ID {$request->project_id} tidak ditemukan.");
                }
            }

            // Tentukan status SPB berdasarkan tanggal_berahir_spb
            $tanggalBerahirSpb = Carbon::parse($request->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            /* $tanggalBerahirSpb = Carbon::parse($request->tanggal_berahir_spb)->timezone('Asia/Jakarta');
            $nowDate = Carbon::now('Asia/Jakarta');   */


            /* $spbStatus = match (true) {
                $spbCategory->id == SpbProject_Category::BORONGAN => SpbProject_Status::AWAITING,
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->isSameDay($tanggalBerahirSpb) => SpbProject_Status::DUEDATE,
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->gt($tanggalBerahirSpb) => SpbProject_Status::OVERDUE,
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->lt($tanggalBerahirSpb) => SpbProject_Status::OPEN,
                $request->type_project == SpbProject::TYPE_PROJECT_SPB && $spbCategory->id == SpbProject_Category::INVOICE => SpbProject_Status::AWAITING,
                default => SpbProject_Status::AWAITING, 
            }; */

            $spbStatus = match (true) {
                // Jika kategori SPB adalah BORONGAN, status menjadi AWAITING
                $spbCategory->id == SpbProject_Category::BORONGAN => SpbProject_Status::AWAITING,
                $spbCategory->id == SpbProject_Category::INVOICE => SpbProject_Status::AWAITING,
                $request->type_project == SpbProject::TYPE_PROJECT_SPB && $spbCategory->id == SpbProject_Category::FLASH_CASH => SpbProject_Status::AWAITING,
            
                // Logika untuk tipe proyek NON-PROJECT
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->isSameDay($tanggalBerahirSpb) => SpbProject_Status::DUEDATE,
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->gt($tanggalBerahirSpb) => SpbProject_Status::OVERDUE,
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $nowDate->lt($tanggalBerahirSpb) => SpbProject_Status::OPEN,
            
                // Logika untuk tipe proyek FLASH_CASH
                /* $request->type_project == SpbProject_Category::FLASH_CASH && $nowDate->isSameDay($tanggalBerahirSpb) => SpbProject_Status::DUEDATE,
                $request->type_project == SpbProject_Category::FLASH_CASH && $nowDate->gt($tanggalBerahirSpb) => SpbProject_Status::OVERDUE,
                $request->type_project == SpbProject_Category::FLASH_CASH && $nowDate->lt($tanggalBerahirSpb) => SpbProject_Status::OPEN, */
            
                // Default status jika kondisi lain tidak terpenuhi
                default => SpbProject_Status::AWAITING,
            };
            
            // Generate doc_no_spb
            $maxDocNo = SpbProject::where('spbproject_category_id', $request->spbproject_category_id)
                ->orderByDesc('doc_no_spb')
                ->first();
            $maxNumericPart = $maxDocNo ? (int) substr($maxDocNo->doc_no_spb, strpos($maxDocNo->doc_no_spb, '-') + 1) : 0;

            $typeTerminSpb = $spbCategory->id == SpbProject_Category::BORONGAN 
            ? SpbProject::TYPE_TERMIN_BELUM_LUNAS 
            : null;

            $company_id = $request->vendor_borongan_id;

            $tab = match (true) {
                // Jika jenis proyek adalah NON-PROJECT dan kategori SPB adalah FLASH_CASH
                $request->type_project == SpbProject::TYPE_NON_PROJECT_SPB && $spbCategory->id == SpbProject_Category::FLASH_CASH => SpbProject::TAB_PAYMENT_REQUEST,
            
                // Untuk proyek biasa (TYPE_PROJECT_SPB) dan kategori lainnya, masuk ke TAB_SUBMIT
                $request->type_project == SpbProject::TYPE_PROJECT_SPB || $spbCategory->id != SpbProject_Category::FLASH_CASH => SpbProject::TAB_SUBMIT,
                default => SpbProject::TAB_SUBMIT,
            };

            // Merge data untuk SPB Project
            $request->merge([
                'doc_no_spb' => $this->generateDocNo($maxNumericPart, $spbCategory),
                'doc_type_spb' => strtoupper($spbCategory->name),
                'spbproject_status_id' => $spbStatus,
                'tab_spb' => $tab,
                /* 'tab_spb' => $spbCategory->id == SpbProject_Category::FLASH_CASH
                    ? SpbProject::TAB_PAYMENT_REQUEST
                    : SpbProject::TAB_SUBMIT, */
                'user_id' => auth()->user()->id,
                'type_termin_spb' => $typeTerminSpb,
                'company_id' => $company_id,
            ]);

            // Buat SPB Project baru
            $spbProject = SpbProject::create($request->only([
                'doc_no_spb',
                'doc_type_spb',
                'type_project',
                'spbproject_category_id',
                'spbproject_status_id',
                'tab_spb',
                'user_id',
                'project_id',
                'unit_kerja',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
                'harga_total_pembayaran_borongan_spb',
                'type_termin_spb',
                'company_id',
                'vendor_borongan_id',
            ]));

             // Proses produk_data
             if ($spbCategory->id != SpbProject_Category::BORONGAN && $request->has('produk_data') && is_array($request->produk_data)) {
                foreach ($request->produk_data as $item) {
                    $dueDate = Carbon::parse($item['due_date']);
                    $status = match (true) {
                        $spbCategory->id == SpbProject_Category::INVOICE => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        $spbCategory->id == SpbProject_Category::FLASH_CASH => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        $nowDate->isSameDay($dueDate) => ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT,
                        $nowDate->gt($dueDate) => ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                        $nowDate->lt($dueDate) => ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                        default => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    }; 

                // Simpan data produk
                ProductCompanySpbProject::create([
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'produk_id' => $item['produk_id'],
                        'company_id' => $item['vendor_id'],
                        'ongkir' => $item['ongkir'] ?? 0,
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'description' => $item['description'],
                        'ppn' => $item['tax_ppn'] ?? 0,
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'status_produk' => $status,
                        'status_vendor' => $status,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB dengan doc_no {$spbProject->doc_no_spb} berhasil dibuat.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeProduk(AddProdukRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

            // **Tentukan Status Produk & Vendor Berdasarkan tab_spb**
            $statusProduk = ($spbProject->tab_spb == SpbProject::TAB_PAID) 
                ? ProductCompanySpbProject::TEXT_PAID_PRODUCT 
                : ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;

            $statusVendor = ($spbProject->tab_spb == SpbProject::TAB_PAID) 
                ? ProductCompanySpbProject::TEXT_PAID_PRODUCT 
                : ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;

            // Proses setiap produk yang dikirim dalam request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];

                // Cari apakah produk sudah ada di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();

                if ($product) {
                    // Jika produk sudah ada, update datanya
                    $product->update([
                        'harga' => $produkData['harga'],
                        'stok' => $produkData['stok'],
                        'tax_ppn' => $produkData['tax_ppn'] ?? 0,
                        'ongkir' => $produkData['ongkir'] ?? 0,
                        'date' => $produkData['date'],
                        'due_date' => $produkData['due_date'],
                        'status_produk' => $statusProduk, // Kondisi PAID jika tab_spb PAID
                        'status_vendor' => $statusVendor, // Kondisi PAID jika tab_spb PAID
                    ]);
                } else {
                    // Jika produk belum ada, tambahkan sebagai entri baru
                    ProductCompanySpbProject::create([
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'produk_id' => $produkId,
                        'company_id' => $vendorId,
                        'ongkir' => $produkData['ongkir'] ?? 0,
                        'harga' => $produkData['harga'],
                        'stok' => $produkData['stok'],
                        'description' => $produkData['description'] ?? null,
                        'ppn' => $produkData['tax_ppn'] ?? 0,
                        'date' => $produkData['date'],
                        'due_date' => $produkData['due_date'],
                        'status_produk' => $statusProduk, // Kondisi PAID jika tab_spb PAID
                        'status_vendor' => $statusVendor, // Kondisi PAID jika tab_spb PAID
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Produk berhasil ditambahkan atau diperbarui ke SPB Project {$spbProject->doc_no_spb}.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /* public function storeProduk(AddProdukRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

            // Proses setiap produk yang dikirim dalam request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];

                // Cari apakah produk sudah ada di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();

                if ($product) {
                    // Jika produk sudah ada, update datanya
                    $product->update([
                        'harga' => $produkData['harga'],
                        'stok' => $produkData['stok'],
                        'tax_ppn' => $produkData['tax_ppn'] ?? 0,
                        'ongkir' => $produkData['ongkir'] ?? 0,
                        'date' => $produkData['date'],
                        'due_date' => $produkData['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, 
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                } else {
                    // Jika produk belum ada, tambahkan sebagai entri baru
                    ProductCompanySpbProject::create([
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'produk_id' => $produkId,
                        'company_id' => $vendorId,
                        'ongkir' => $produkData['ongkir'] ?? 0,
                        'harga' => $produkData['harga'],
                        'stok' => $produkData['stok'],
                        'description' => $produkData['description'] ?? null,
                        'ppn' => $produkData['tax_ppn'] ?? 0,
                        'date' => $produkData['date'],
                        'due_date' => $produkData['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, 
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Produk berhasil ditambahkan atau diperbarui ke SPB Project {$spbProject->doc_no_spb}.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    } */

    /* protected function generateDocNo($maxNumericPart, $spbCategory)
    {
        // Pastikan kategori SPB memiliki format yang benar
        if (!$spbCategory || !isset($spbCategory->short)) {
            throw new \Exception("Kategori SPB tidak valid atau tidak ditemukan.");
        }

        // Jika tidak ada doc_no_spb sebelumnya, mulai dari nomor 001
        if ($maxNumericPart === 0) {
            return "{$spbCategory->short}-001";
        }

        // Tambahkan 1 pada bagian numerik dan format menjadi 3 digit
        $nextNumber = sprintf('%03d', $maxNumericPart + 1);
        return "{$spbCategory->short}-$nextNumber";
    } */
    

    /* protected function generateDocNo($maxNumericPart, $spbCategory)
    {
        // Pastikan kategori SPB memiliki format yang benar
        if (!$spbCategory || !isset($spbCategory->short)) {
            throw new \Exception("Kategori SPB tidak valid atau tidak ditemukan.");
        }

        // Jika tidak ada doc_no_spb sebelumnya, mulai dari nomor 0001
        if ($maxNumericPart === 0) {
            return "{$spbCategory->short}-0001";
        }

        // Tambahkan 1 pada bagian numerik dan format menjadi 4 digit
        $nextNumber = sprintf('%04d', $maxNumericPart + 1);  // Pastikan ini menghasilkan 4 digit
        $docNo = "{$spbCategory->short}-$nextNumber";

        // Periksa apakah doc_no_spb sudah ada di database
        $exists = SpbProject::where('doc_no_spb', $docNo)->exists();
        
        // Jika ada duplikasi, teruskan pencarian hingga nomor yang unik ditemukan
        while ($exists) {
            $nextNumber = sprintf('%04d', $maxNumericPart + 1); // Tambah nomor lagi jika ada duplikasi
            $docNo = "{$spbCategory->short}-$nextNumber";
            $exists = SpbProject::where('doc_no_spb', $docNo)->exists();
            $maxNumericPart++;
        }

        return $docNo;
    } */

    /* protected function generateDocNo($maxNumericPart, $spbCategory)
    {
        // Pastikan kategori SPB memiliki format yang benar
        if (!$spbCategory || !isset($spbCategory->short)) {
            throw new \Exception("Kategori SPB tidak valid atau tidak ditemukan.");
        }

        // Jika tidak ada doc_no_spb sebelumnya, mulai dari nomor 0001
        if ($maxNumericPart === 0) {
            return "{$spbCategory->short}-0001";
        }

        // Tambahkan 1 pada bagian numerik dan format menjadi 4 digit
        $nextNumber = sprintf('%04d', $maxNumericPart + 1);  // Pastikan ini menghasilkan 4 digit
        $docNo = "{$spbCategory->short}-$nextNumber";

        // Periksa apakah doc_no_spb sudah ada di database
        $exists = SpbProject::where('doc_no_spb', $docNo)->exists();

        // Jika ada duplikasi, teruskan pencarian hingga nomor yang unik ditemukan
        while ($exists) {
            $maxNumericPart++;  // Increment the max numeric part
            $nextNumber = sprintf('%04d', $maxNumericPart); // Ensure this generates a 4-digit number
            $docNo = "{$spbCategory->short}-$nextNumber";
            $exists = SpbProject::where('doc_no_spb', $docNo)->exists(); // Check again for duplication
        }

        return $docNo;
    } */

    protected function generateDocNo($maxNumericPart, $spbCategory)
    {
        if (!$spbCategory || !isset($spbCategory->short)) {
            throw new \Exception("Kategori tidak valid atau tidak ditemukans.");
        }

        // Jika tidak ada doc_no_spb sebelumnya, mulai dari nomor 0001
        if ($maxNumericPart === 0) {
            return "{$spbCategory->short}-0001";
        }

        // Tambahkan 1 pada bagian numerik dan format menjadi 4 digit
        $nextNumber = sprintf('%04d', $maxNumericPart + 1);
        $docNo = "{$spbCategory->short}-$nextNumber";

        // Periksa apakah doc_no_spb sudah ada, termasuk yang soft deleted
        $exists = SpbProject::withTrashed()->where('doc_no_spb', $docNo)->exists();

        // Jika ada duplikasi, teruskan pencarian hingga nomor yang unik ditemukan
        while ($exists) {
            $maxNumericPart++;  // Tambahkan nomor
            $nextNumber = sprintf('%04d', $maxNumericPart);
            $docNo = "{$spbCategory->short}-$nextNumber";
            $exists = SpbProject::withTrashed()->where('doc_no_spb', $docNo)->exists();
        }

        return $docNo;
    }

    /* public function update(UpdateRequest $request, $docNoSpb)
    {
        DB::beginTransaction();

        try {
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukans");
            }

            // Mendapatkan SpbProject yang akan diperbarui
            $spbProject = SpbProject::with('documents')->where('doc_no_spb', $docNoSpb)->first();
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found.',
                ], 404);
            }

             // Jika project_id kosong, set menjadi null
            if (empty($request->project_id)) {
                $request->merge(['project_id' => null]);
            }

            $company_id = $request->vendor_borongan_id;

            // Mengupdate data SPB Project sesuai dengan input pada request
            $spbProject->update($request->only([
                'doc_type_spb',
                'spbproject_category_id',
                'project_id',
                'unit_kerja',
                'type_project',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
                'harga_total_pembayaran_borongan_spb',
                'harga_termin_spb',
                'deskripsi_termin_spb',
                'type_termin_spb',
                'company_id',
                'vendor_borongan_id',
            ]));

            if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN && $company_id) {
                $spbProject->company_id = $company_id; // Update company_id dengan vendor_borongan_id
                $spbProject->save();
            }

            // Menyimpan atau mengganti file attachment jika ada
            if ($request->hasFile('attachment_file_spb')) {
                foreach ($request->file('attachment_file_spb') as $key => $file) {
                    if ($file->isValid()) {
                        $this->replaceDocument($spbProject, $file, $key + 1);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'File upload failed',
                        ], 400);
                    }
                }
            }

            // Commit transaksi jika semua berhasil
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$spbProject->doc_no_spb} has been updated successfully.",
            ]);
        } catch (\Throwable $th) {
            // Rollback transaksi jika ada error
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    } */

    public function update(UpdateRequest $request, $docNoSpb)
    {
        DB::beginTransaction();

        try {
            /*
             |--------------------------------------------------------------
             | 1. Ambil model dan pastikan ada
             |--------------------------------------------------------------
             */
            $spbProject = SpbProject::with('documents')
                ->where('doc_no_spb', $docNoSpb)
                ->firstOrFail();  // 404 otomatis jika tidak ada

            /*
             |--------------------------------------------------------------
             | 2. (Opsional) Validasi kategori bila dikirim di request
             |--------------------------------------------------------------
             */
            if ($request->filled('spbproject_category_id')) {
                $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
                if (!$spbCategory) {
                    throw new \Exception('Kategori SPB tidak ditemukan');
                }
            }

            /*
             |--------------------------------------------------------------
             | 3. Siapkan data yang akan di-fill
             |--------------------------------------------------------------
             */
            /* if (empty($request->project_id)) {
                $request->merge(['project_id' => null]);
            } */

            $fillable = [
                'doc_type_spb',
                'spbproject_category_id',
                'project_id',
                'unit_kerja',
                'type_project',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
                'harga_total_pembayaran_borongan_spb',
                'harga_termin_spb',
                'deskripsi_termin_spb',
                'type_termin_spb',
                'company_id',
                'vendor_borongan_id',
            ];

            /*
             |--------------------------------------------------------------
             | 4. Bekukan timestamp jika sudah Paid
             |--------------------------------------------------------------
             */
            $freezeTimestamp = $spbProject->tab_spb == SpbProject::TAB_PAID;
            if ($freezeTimestamp) {
                $spbProject->timestamps = false;           // matikan auto-touch
            }

            $spbProject->fill($request->only($fillable));

            // Khusus SPB Borongan: sinkronkan company_id jika vendor_borongan_id ada
            if (
                $spbProject->spbproject_category_id == SpbProject_Category::BORONGAN &&
                $request->filled('vendor_borongan_id')
            ) {
                $spbProject->company_id = $request->vendor_borongan_id;
            }

            $spbProject->save();  // timestamps false → updated_at TIDAK berubah

            /*
             |--------------------------------------------------------------
             | 5. Simpan / ganti lampiran jika ada
             |--------------------------------------------------------------
             */
            if ($request->hasFile('attachment_file_spb')) {
                foreach ($request->file('attachment_file_spb') as $idx => $file) {
                    if ($file->isValid()) {
                        $this->replaceDocument($spbProject, $file, $idx + 1);
                    } else {
                        throw new \Exception('File upload failed');
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "SPB Project {$spbProject->doc_no_spb} has been updated successfully.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengganti dokumen lama dengan dokumen baru.
     */
    protected function replaceDocument($spbProject, $file, $iteration)
    {
        // Simpan file baru
        $documentPath = $file->store(SpbProject::ATTACHMENT_FILE_SPB, 'public');

        // Hapus file lama jika ada
        $existingDocument = $spbProject->documents()
            ->where('file_name', "{$spbProject->doc_no_spb}.{$iteration}")
            ->first();

        if ($existingDocument) {
            Storage::delete($existingDocument->file_path);
            $existingDocument->delete();
        }

        // Simpan informasi dokumen baru
        return $spbProject->documents()->create([
            "doc_no_spb" => $spbProject->doc_no_spb,
            "file_name" => $spbProject->doc_no_spb . '.' . $iteration,
            "file_path" => $documentPath,
        ]);
    }

    /* public function updateproduk(UpdateProdukRequest $request, $id)
    {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Ambil produk baru dari request
            $produkData = $request->input('produk', []);
    
            // Hapus semua data lama yang ada di tabel productCompanySpbprojects untuk SPB Project ini
            $spbProject->productCompanySpbprojects()->delete();
    
            // Tambahkan data baru berdasarkan request
            foreach ($produkData as $item) {
                $spbProject->productCompanySpbprojects()->create([
                    'company_id' => $item['vendor_id'],
                    'produk_id' => $item['produk_id'],
                    'harga' => $item['harga'] ?? 0,
                    'stok' => $item['stok'],
                    'ppn' => $item['tax_ppn'] ?? 0,
                    'description' => $item['description'],
                    'ongkir' => $item['ongkir'] ?? 0,
                    'date' => $item['date'],
                    'due_date' => $item['due_date'],
                    // 'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    // 'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                ]);
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been updated successfully. All old products and vendors were removed.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }     */

     public function updateproduk(UpdateProdukRequest $request, $docNoSpb)
    {
        DB::beginTransaction();

        try {
            /* ----------------------------------------------------------
            * 1. Ambil SPB dan koleksi produk eksisting
            * ---------------------------------------------------------- */
            $spbProject = SpbProject::with('productCompanySpbprojects')
                ->where('doc_no_spb', $docNoSpb)
                ->firstOrFail();

            // Key-kan produk lama: vendorID-produkID → model
            $oldRows = $spbProject->productCompanySpbprojects
                ->keyBy(fn ($row) => $row->company_id . '-' . $row->produk_id);

            /* ----------------------------------------------------------
            * 2. Loop payload dari request
            * ---------------------------------------------------------- */
            $payloadRows = collect($request->input('produk', []));

            foreach ($payloadRows as $item) {

                $key = $item['vendor_id'] . '-' . $item['produk_id'];
                $old = $oldRows->get($key);     // null jika baris baru

                /* --- siapkan data yang akan disimpan --- */
                $data = [
                    'company_id' => $item['vendor_id'],
                    'produk_id'  => $item['produk_id'],
                    'harga'      => $item['harga'] ?? 0,
                    'stok'       => $item['stok'],
                    'ppn'        => $item['tax_ppn'] ?? 0,
                    'ongkir'     => $item['ongkir'] ?? 0,
                    'description'=> $item['description'] ?? null,
                    'date'       => $item['date']       ?? null,
                    'due_date'   => $item['due_date']   ?? null,
                ];

                /* --- status: pakai yang lama jika ada; jika tidak, hitung --- */
                $data['status_produk'] = $old?->status_produk
                    ?? $this->determineStatus($data['date'], $data['due_date']);

                $data['status_vendor'] = $old?->status_vendor
                    ?? $data['status_produk']; // vendor biasanya mirror produk

                /* --- update atau create --- */
                ProductCompanySpbProject::updateOrCreate(
                    [
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'company_id'     => $data['company_id'],
                        'produk_id'      => $data['produk_id'],
                    ],
                    $data
                );

                // tandai bahwa baris ini sudah diproses ⇒ jangan dihapus
                $oldRows->forget($key);
            }

            /* ----------------------------------------------------------
            * 3. (Opsional) hapus baris lama yang tidak ada di payload
            * ---------------------------------------------------------- */
            if ($oldRows->isNotEmpty()) {
                $idsToDelete = $oldRows->pluck('id');
                ProductCompanySpbProject::whereIn('id', $idsToDelete)->delete();
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "SPB Project {$docNoSpb} produk berhasil diperbarui.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tentukan default status berdasarkan tanggal & due_date.
     * - jika sudah dibayar manual, Anda cukup lempar 'Paid'.
     */
    private function determineStatus(?string $date, ?string $dueDate): string
    {
        $today = \Carbon\Carbon::today();

        // Buat contoh logika sederhana:
        if ($dueDate) {
            $due = \Carbon\Carbon::parse($dueDate);
            if ($today->gt($due)) {
                return ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;   // Lewat batas
            }
            if ($today->eq($due)) {
                return ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;   // Hari H
            }
            return ProductCompanySpbProject::TEXT_OPEN_PRODUCT;          // Sebelum due
        }

        // Tanpa due_date: mulai dari Awaiting
        return ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;
    }

    public function addspbtoproject(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Temukan proyek berdasarkan ID
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found.',
                ], 404);
            }

            // Validasi array doc_no_spb yang diterima dalam request
            $docNos = $request->input('doc_no_spb', []);

            if (empty($docNos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No SPB doc_no_spb provided.',
                ], 400);
            }

            // Loop setiap doc_no_spb dan tambahkan ke proyek yang dipilih
            foreach ($docNos as $docNo) {
                $SpbProject = SpbProject::where('doc_no_spb', $docNo)->first();

                if (!$SpbProject) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "SPB Project with doc_no_spb {$docNo} not found.",
                    ], 404);
                }

                // Tambahkan relasi many-to-many ke proyek
                $project->spbProjects()->attach($SpbProject->doc_no_spb, ['project_id' => $project->id]);

                // Buat atau update log status
                $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_VERIFIED)
                                                    ->where('name', auth()->user()->name)
                                                    ->first();

                if ($existingLog) {
                    // Update log yang sudah ada
                    $existingLog->update([
                        'message' => 'SPB Project has been accepted.',
                        'updated_at' => now(),
                    ]);
                } else {
                    // Buat log baru jika belum ada
                    LogsSPBProject::create([
                        'spb_project_id' => $SpbProject->doc_no_spb,
                        'tab' => SpbProject::TAB_VERIFIED,
                        'name' => auth()->user()->name,
                        'message' => 'SPB Project has been accepted.',
                    ]);
                }
            }

            // Commit transaksi
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Projects have been successfully assigned to the project.",
            ], 200);

        } catch (\Throwable $th) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /* public function destroy($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }
    
        // Pastikan doc_no_spb digunakan, bukan id
        $spbProjectDocNo = $spbProject->doc_no_spb;  // Menggunakan doc_no_spb sebagai referensi
    
        try {
            // Hapus dokumen yang terkait dengan SPB (jika ada)
            $document = DocumentSPB::where('doc_no_spb', $spbProjectDocNo)->first();
            if ($document) {
                // Hapus file dari storage
                Storage::delete($document->file_path);
    
                // Soft delete dokumen
                $document->delete();
            }
    
            // Hapus data log yang terkait dengan SpbProject
            $spbProject->logs()->delete();  // Hapus semua log terkait SpbProject
    
            // Menambahkan log penghapusan ke dalam logs_spbprojects
            DB::table('logs_spbprojects')->insert([
                'spb_project_id' => $spbProjectDocNo,
                'message' => 'SPB deleted by ' . auth()->user()->name,
                'deleted_at' => now(),
                'deleted_by' => auth()->user()->name,
                'tab_spb' => $spbProject->tab_spb,
                'name' => auth()->user()->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    
            // Soft delete untuk SpbProject
            $spbProject->delete();
    
            // Commit transaksi
            DB::commit();
    
            return MessageActeeve::success("SpbProject $docNoSpb and related document have been deleted");
        } catch (\Throwable $th) {
            // Rollback jika terjadi error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }    */ 

    public function destroy($docNoSpb)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Pastikan doc_no_spb digunakan, bukan id
        $spbProjectDocNo = $spbProject->doc_no_spb;  // Menggunakan doc_no_spb sebagai referensi

        try {
            $spbProject->documents()->each(function ($document) {
                // Hapus file dari storage
                Storage::delete($document->file_path);
    
                // Soft delete dokumen
                $document->delete();
            });
            
            // Hapus data log yang terkait dengan SpbProject
            $spbProject->logs()->delete();  // Hapus semua log terkait SpbProject

            // Menambahkan log penghapusan ke dalam logs_spbprojects
            DB::table('logs_spbprojects')->insert([
                'spb_project_id' => $spbProjectDocNo,  // Menggunakan doc_no_spb sebagai referensi
                'message' => 'SPB deleted by ' . auth()->user()->name,
                'deleted_at' => now(), // Waktu penghapusan
                'deleted_by' => auth()->user()->name, // Nama pengguna yang menghapus
                'tab_spb' => $spbProject->tab_spb,  // Menambahkan tab_spb dari spbProject
                'name' => auth()->user()->name,  // Pastikan kolom name terisi dengan nama pengguna
                'created_at' => now(), // Tambahkan ini untuk sorting
                'updated_at' => now(), // Tambahkan ini
            ]);

            // Hapus SpbProject itu sendiri (soft delete)
            $spbProject->delete();
            //  Cache::forget('latest_log_date');

            DB::commit();
            return MessageActeeve::success("SpbProject $docNoSpb has been deleted");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }


    public function showNotLogin($id)
    {
        // Ambil project berdasarkan ID dengan relasi yang diperlukan
        $spbProject = SpbProject::with(['logs', 'project', 'productCompanySpbprojects', 'user'])->find($id);

        // Cek apakah proyek ditemukan
        if (!$spbProject) {
            return response()->json(['error' => 'Data not found'], 404);
        }

        // Tentukan nama tab berdasarkan konstanta
        $tabNames = [
            SpbProject::TAB_SUBMIT => 'Submit',
            SpbProject::TAB_VERIFIED => 'Verified',
            SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
            SpbProject::TAB_PAID => 'Paid',
        ];

        $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';

        // Menentukan nama berdasarkan type_project
        $typeSpbProject = [
            'id' => $spbProject->type_project,
            'name' => $spbProject->type_project == SpbProject::TYPE_PROJECT_SPB
                ? SpbProject::TEXT_PROJECT_SPB
                : SpbProject::TEXT_NON_PROJECT_SPB,
        ];

        $company = $spbProject->company;

        // Siapkan data proyek untuk dikembalikan
        $data = [
            "doc_no_spb" => $spbProject->doc_no_spb,
            "doc_type_spb" => $spbProject->doc_type_spb,
            "is_payment_vendor" => (bool) $spbProject->is_payment_vendor,
            "status_spb" => $this->getStatus($spbProject),
            'logs_spb' => $spbProject->logs->groupBy('name')->map(function ($logsByUser) use ($spbProject) {
                $lastLog = $logsByUser->sortByDesc('created_at')->first();

                return [
                    'tab_spb' => $lastLog->tab_spb,
                    'name' => $lastLog->name,
                    'created_at' => $lastLog->created_at,
                    'message' => $lastLog->message,
                    'reject_note' => $spbProject->reject_note,
                ];
            })->values()->all(),
            "type_spb_project" => $typeSpbProject,
            'supervisor' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                ? $spbProject->project->tenagaKerja()
                    ->whereHas('role', function ($query) {
                        $query->where('role_name', 'Supervisor'); 
                    })
                    ->first() 
                    ? [
                        'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                            $query->where('role_name', 'Supervisor');
                        })->first())->id ?? null,
                        'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                            $query->where('role_name', 'Supervisor');
                        })->first())->name ?? null,
                        'divisi' => [
                            'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first()->divisi)->id,
                            'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first()->divisi)->name,
                        ],
                    ]
                    : null 
                : null, 
               /*  'tukang' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                    ? $spbProject->project->tenagaKerja()
                        ->whereHas('role', function ($query) {
                            $query->whereIn('role_name', ['Owner', 'Marketing', 'Supervisor']);
                        })
                        ->get() 
                        ->map(function ($user) {
                            return [
                                'id' => $user->id ?? null,
                                'name' => $user->name ?? null,
                                'divisi' => [
                                    'id' => optional($user->divisi)->id,
                                    'name' => optional($user->divisi)->name,
                                ],
                            ];
                        })
                    : [],  */
            "project" => $spbProject->project ? [
                'id' => $spbProject->project->id,
                'nama' => $spbProject->project->name,
            ] : null,
                'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                    $dueDate = Carbon::createFromFormat("Y-m-d", $product->due_date); // Membaca due_date
                    $nowDate = Carbon::now(); // Mendapatkan tanggal sekarang
                    $status = $product->status_produk; 
                    $status = $product->status_vendor;

                    // Periksa jika status produk adalah "Paid"
                    if ($status === ProductCompanySpbProject::TEXT_PAID_PRODUCT) {
                        // Jika status adalah "Paid", set status ke "Paid"
                        $notePaid = $product->note_paid_produk; // Ambil note_paid_produk jika statusnya "Paid"
                        return [
                            'produk_data' => [
                                'produk_id' => $product->produk_id ?? null,
                                'nama' => $product->product->nama ?? null,
                                'id_kategori' => $product->product->id_kategori ?? null,
                                'type_pembelian' => $product->product->type_pembelian ?? null,
                                'harga_product' => $product->product->harga_product ?? null,
                            ],
                            'vendor' => [
                                'id' => $product->company->id ?? 'Unknown',
                                'name' => $product->company->name ?? 'Unknown',
                                'bank_name' => $product->company->bank_name ?? 'Unknown',
                                'account_name' => $product->company->account_name ?? 'Unknown',
                            ],
                            'total_vendor' => $product->getTotalVendorAttribute(),
                            'status_vendor' => $status,
                            'status_produk' => $status, // Status produk adalah "Paid"
                            'note_paid_produk' => $notePaid, // Catatan jika produk sudah dibayar
                            'date' => $product->date,
                            'due_date' => $product->due_date,
                            'description' => $product->description,
                            'ppn' => $product->ppn_detail, 
                            'ongkir' => $product->ongkir ?? 0,
                            'harga' => $product->harga ?? 0,
                            'stok' => $product->stok ?? 0,
                            'subtotal_item' => $product->subtotal_produk,
                            'payment_date' => $product->payment_date ?? null,  
                            'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                            /* 'pph' => [
                                'pph_type' => $product->taxPph->name ?? 'Unknown',
                                'pph_rate' => $product->taxPph->percent ?? 0,
                                'pph_hasil' => $product->pph_value,
                            ], */
                            // 'total_item' => $product->total_produk,
                        ];
                    }

                    // Cek jika produk sudah ditolak (Rejected), maka langsung set statusnya ke Rejected
                    if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                        $noteReject = $product->note_reject_produk; // Ambil note_reject_produk jika statusnya "Rejected"
                        return [
                            'produk_data' => [
                                'produk_id' => $product->produk_id ?? null,
                                'nama' => $product->product->nama ?? null,
                                'id_kategori' => $product->product->id_kategori ?? null,
                                'type_pembelian' => $product->product->type_pembelian ?? null,
                                'harga_product' => $product->product->harga_product ?? null,
                            ],
                            'vendor' => [
                                'id' => $product->company->id ?? 'Unknown',
                                'name' => $product->company->name ?? 'Unknown',
                                'bank_name' => $product->company->bank_name ?? 'Unknown',
                                'account_name' => $product->company->account_name ?? 'Unknown',
                            ],
                            'total_vendor' => $product->getTotalVendorAttribute(),
                            'status_vendor' => $status,
                            'status_produk' => $status, // Status produk adalah "Rejected"
                            'note_reject_produk' => $noteReject, // Catatan ditolak
                            'date' => $product->date,
                            'due_date' => $product->due_date,
                            'description' => $product->description,
                            'ppn' => $product->ppn_detail, 
                            'ongkir' => $product->ongkir ?? 0,
                            'harga' => $product->harga ?? 0,
                            'stok' => $product->stok ?? 0,
                            'subtotal_item' => $product->subtotal_produk,
                            'payment_date' => $product->payment_date ?? null,  
                            'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                            /* 'pph' => [
                                'pph_type' => $product->taxPph->name ?? 'Unknown',
                                'pph_rate' => $product->taxPph->percent ?? 0,
                                'pph_hasil' => $product->pph_value,
                            ], */
                            // 'total_item' => $product->total_produk,
                        ];
                    }

                    /* // Periksa apakah status produk bukan open, overdue, atau duedate
                    if (!in_array($status, [
                        ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                        ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                        ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT
                    ])) {
                        // Jika status produk bukan open, overdue, atau duedate, set status ke Awaiting
                        $status = ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;
                    } else {
                        // Jika status produk valid, periksa status berdasarkan due_date dan tab_spb
                        $dueDateDayYear = $dueDate->format('d-Y'); // Format tanggal hanya hari dan tahun
                        $nowDateDayYear = $nowDate->format('d-Y'); // Tanggal sekarang (hari dan tahun)

                        // Periksa status berdasarkan due_date dan tab_spb hanya jika status belum "Awaiting"
                        if ($status !== ProductCompanySpbProject::TEXT_AWAITING_PRODUCT) {
                            if ($spbProject->tab_spb == SpbProject::TAB_VERIFIED || $spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
                                if ($nowDateDayYear > $dueDateDayYear) {
                                    // Jika tanggal sekarang lebih besar dari due_date (terlambat), set status ke OVERDUE
                                    $status = ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;
                                } elseif ($nowDateDayYear == $dueDateDayYear) {
                                    // Jika tanggal sekarang sama dengan due_date (tepat waktu), set status ke DUEDATE
                                    $status = ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;
                                } elseif ($nowDateDayYear < $dueDateDayYear) {
                                    // Jika tanggal sekarang lebih kecil dari due_date (belum lewat), set status ke OPEN
                                    $status = ProductCompanySpbProject::TEXT_OPEN_PRODUCT;
                                }
                            }
                        }
                    } */

                    // Menangani status "Rejected" jika tidak ditemukan sebelumnya
                    if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                        $noteReject = $product->note_reject_produk;
                    } else {
                        $noteReject = null;
                    }

                    return [
                        'produk_data' => [
                            'produk_id' => $product->produk_id ?? null,
                            'nama' => $product->product->nama ?? null,
                            'id_kategori' => $product->product->id_kategori ?? null,
                            'type_pembelian' => $product->product->type_pembelian ?? null,
                            'harga_product' => $product->product->harga_product ?? null,
                        ],
                        'vendor' => [
                            'id' => $product->company->id ?? 'Unknown',
                            'name' => $product->company->name ?? 'Unknown',
                            'bank_name' => $product->company->bank_name ?? 'Unknown',
                            'account_name' => $product->company->account_name ?? 'Unknown',
                        ],
                        'total_vendor' => $product->getTotalVendorAttribute(),
                        'status_vendor' => $product->status_vendor,
                        'status_produk' => $status,
                        'note_reject_produk' => $noteReject,
                    'date' => $product->date,
                    'due_date' => $product->due_date,
                    'description' => $product->description,
                    'ppn' => $product->ppn_detail, 
                    'ongkir' => $product->ongkir ?? 0,
                    'harga' => $product->harga ?? 0,
                    'stok' => $product->stok ?? 0,
                    'subtotal_item' => $product->subtotal_produk,
                    'payment_date' => $product->payment_date ?? null,  
                    'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                    /* 'pph' => [
                        'pph_type' => $product->taxPph->name ?? 'Unknown',
                        'pph_rate' => $product->taxPph->percent ?? 0,
                        'pph_hasil' => $product->pph_value,
                    ], */
                    // 'total_item' => $product->total_produk,
                ];
            }),
            "total" => $spbProject->total_produk,
            'sisa_pembayaran' => $spbProject->sisaPembayaranProductVendor(),
            'total_terbayarkan' => $spbProject->totalTerbayarProductVendor(),
            'file_attachement' => $this->getDocument($spbProject),
            'unit_kerja' => $spbProject->unit_kerja,
            'tanggal_dibuat_spb' => $spbProject->tanggal_dibuat_spb,
            'tanggal_berahir_spb' => $spbProject->tanggal_berahir_spb,
            "harga_total_pembayaran_borongan_spb" => $spbProject->harga_total_pembayaran_borongan_spb ?? null,
            "vendor_borongan" => $company ? [
                    "id" => $company->id,
                    "name" => $company->name,
                    "bank_name" => $company->bank_name,
                    "account_name" => $company->account_name,
                ] : null,
            'sisa_pembayaran_termin_spb' => $this->getDataSisaPemabayaranTerminSpb($spbProject),
            "harga_total_termin_spb" => $this->getHargaTerminSpb($spbProject),
            "deskripsi_termin_spb" => $spbProject->deskripsi_termin_spb ?? null,
            "riwayat_termin" => $this->getRiwayatTermin($spbProject),
            "type_termin_spb" => $this->getDataTypetermin($spbProject->type_termin_spb),
            "know_spb_marketing" => $this->getUserRole($spbProject->know_marketing),
            "know_spb_supervisor" => $this->getUserRole($spbProject->know_supervisor),
            "know_spb_kepalagudang" => $this->getUserRole($spbProject->know_kepalagudang),
            "accept_spb_finance" => $this->getUserRole($spbProject->know_finance), 
            "payment_request_owner" => $spbProject->request_owner ? $this->getUserRole($spbProject->request_owner) : null,
            "created_at" => $spbProject->created_at->format('Y-m-d'),
            // "updated_at" => $spbProject->updated_at->format('Y-m-d'),
            "updated_at" =>  $this->getUpdatedAt($spbProject),
            "created_by" => $spbProject->user ? [
                "id" => $spbProject->user->id,
                "name" => $spbProject->user->name,
                "created_at" => $spbProject->created_at->timezone('Asia/Jakarta')->toDateTimeString(),
            ] : null,
        ];

        // Kembalikan data dalam format JSON
        return response()->json($data);
    }


    public function show($id)
    {
        // Ambil project berdasarkan ID
        $spbProject = SpbProject::find($id);

        // Cek apakah proyek ditemukan
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

         // Determine the tab name
    $tabNames = [
        SpbProject::TAB_SUBMIT => 'Submit',
        SpbProject::TAB_VERIFIED => 'Verified',
        SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
        SpbProject::TAB_PAID => 'Paid',
    ];

    $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';

    // Menentukan nama berdasarkan type_project
    $typeSpbProject = [
        'id' => $spbProject->type_project,
        'name' => $spbProject->type_project == SpbProject::TYPE_PROJECT_SPB
            ? SpbProject::TEXT_PROJECT_SPB
            : SpbProject::TEXT_NON_PROJECT_SPB,
    ];

    $company = $spbProject->company;

    // Siapkan data proyek untuk dikembalikan
    $data = [
            "doc_no_spb" => $spbProject->doc_no_spb,
            "doc_type_spb" => $spbProject->doc_type_spb,
            // "is_payment_vendor" => $spbProject->is_payment_vendor === null 
            // ? null 
            // : (bool) $spbProject->is_payment_vendor,
            "is_payment_vendor" => (bool) $spbProject->is_payment_vendor,
            "status_spb" => $this->getStatus($spbProject),
            'logs_spb' => $spbProject->logs->groupBy('name')->map(function ($logsByUser) use ($spbProject) {
                // Ambil log terakhir berdasarkan created_at untuk setiap pengguna
                $lastLog = $logsByUser->sortByDesc('created_at')->first();

                // Ambil reject_note dari spbProject
                $rejectNote = $spbProject->reject_note;

                return [
                    'tab_spb' => $lastLog->tab_spb,
                    'name' => $lastLog->name,
                    'created_at' => $lastLog->created_at,
                    'message' => $lastLog->message,
                    'reject_note' => $rejectNote,
                ];
            })->values()->all(),
            "type_spb_project" => $typeSpbProject,
            'supervisor' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                ? $spbProject->project->tenagaKerja()
                    ->whereHas('role', function ($query) {
                        $query->where('role_name', 'Supervisor'); // Filter berdasarkan role 'Supervisor'
                    })
                    ->first() // Ambil hanya supervisor pertama yang ada
                    ? [
                        'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                            $query->where('role_name', 'Supervisor');
                        })->first())->id ?? null,
                        'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                            $query->where('role_name', 'Supervisor');
                        })->first())->name ?? null,
                        'divisi' => [
                            'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first()->divisi)->id,
                            'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first()->divisi)->name,
                        ],
                    ]
                    : null 
                : null, 
                /* 'tukang' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                    ? $spbProject->project->tenagaKerja()
                        ->whereHas('role', function ($query) {
                            // Filter berdasarkan role yang diinginkan
                            $query->whereIn('role_name', ['Owner', 'Marketing', 'Supervisor']);
                        })
                        ->get() // Menambahkan get() untuk mengambil koleksi
                        ->map(function ($user) {
                            return [
                                'id' => $user->id ?? null,
                                'name' => $user->name ?? null,
                                'divisi' => [
                                    'id' => optional($user->divisi)->id, // Pastikan divisi bisa null jika tidak ada
                                    'name' => optional($user->divisi)->name,
                                ],
                            ];
                        })
                    : [],  */
            "project" => $spbProject->project ? [
                'id' => $spbProject->project->id,
                'nama' => $spbProject->project->name,
            ] : [
                'id' => null,
                'nama' => null,
            ],
            'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                $dueDate = Carbon::createFromFormat("Y-m-d", $product->due_date); // Membaca due_date
                $nowDate = Carbon::now(); // Mendapatkan tanggal sekarang
                $status = $product->status_produk; // Status awal produk
                $status = $product->status_vendor; 

                // Periksa jika status produk adalah "Paid"
                if ($status === ProductCompanySpbProject::TEXT_PAID_PRODUCT) {
                    // Jika status adalah "Paid", set status ke "Paid"
                    $notePaid = $product->note_paid_produk; // Ambil note_paid_produk jika statusnya "Paid"
                    return [
                        'produk_data' => [
                            'produk_id' => $product->produk_id ?? null,
                            'nama' => $product->product->nama ?? null,
                            'id_kategori' => $product->product->id_kategori ?? null,
                            'type_pembelian' => $product->product->type_pembelian ?? null,
                            'harga_product' => $product->product->harga_product ?? null,
                        ],
                        'vendor' => [
                            'id' => $product->company->id ?? 'Unknown',
                            'name' => $product->company->name ?? 'Unknown',
                            'bank_name' => $product->company->bank_name ?? 'Unknown',
                            'account_name' => $product->company->account_name ?? 'Unknown',
                        ],
                        'total_vendor' => $product->getTotalVendorAttribute(),
                        'status_vendor' => $status,
                        'status_produk' => $status, // Status produk adalah "Paid"
                        'note_paid_produk' => $notePaid, // Catatan jika produk sudah dibayar
                        'date' => $product->date,
                        'due_date' => $product->due_date,
                        'description' => $product->description,
                        'ppn' => $product->ppn_detail, 
                        'ongkir' => $product->ongkir ?? 0,
                        'harga' => $product->harga ?? 0,
                        'stok' => $product->stok ?? 0,
                        'subtotal_item' => $product->subtotal_produk,
                        'payment_date' => $product->payment_date ?? null,  
                        'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                        /* 'pph' => [
                            'pph_type' => $product->taxPph->name ?? 'Unknown',
                            'pph_rate' => $product->taxPph->percent ?? 0,
                            'pph_hasil' => $product->pph_value,
                        ], */
                        // 'total_item' => $product->total_produk,
                    ];
                }

                // Cek jika produk sudah ditolak (Rejected), maka langsung set statusnya ke Rejected
                if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    $noteReject = $product->note_reject_produk; // Ambil note_reject_produk jika statusnya "Rejected"
                    return [
                        'produk_data' => [
                            'produk_id' => $product->produk_id ?? null,
                            'nama' => $product->product->nama ?? null,
                            'id_kategori' => $product->product->id_kategori ?? null,
                            'type_pembelian' => $product->product->type_pembelian ?? null,
                            'harga_product' => $product->product->harga_product ?? null,
                        ],
                        'vendor' => [
                            'id' => $product->company->id ?? 'Unknown',
                            'name' => $product->company->name ?? 'Unknown',
                            'bank_name' => $product->company->bank_name ?? 'Unknown',
                            'account_name' => $product->company->account_name ?? 'Unknown',
                        ],
                        'total_vendor' => $product->getTotalVendorAttribute(),
                        'status_vendor' => $status,
                        'status_produk' => $status, // Status produk adalah "Rejected"
                        'note_reject_produk' => $noteReject, // Catatan ditolak
                        'date' => $product->date,
                        'due_date' => $product->due_date,
                        'description' => $product->description,
                        'ppn' => $product->ppn_detail, 
                        'ongkir' => $product->ongkir ?? 0,
                        'harga' => $product->harga ?? 0,
                        'stok' => $product->stok ?? 0,
                        'subtotal_item' => $product->subtotal_produk,
                        'payment_date' => $product->payment_date ?? null,  
                        'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                        /* 'pph' => [
                            'pph_type' => $product->taxPph->name ?? 'Unknown',
                            'pph_rate' => $product->taxPph->percent ?? 0,
                            'pph_hasil' => $product->pph_value,
                        ], */
                        // 'total_item' => $product->total_produk,
                    ];
                }

                /* // Periksa apakah status produk bukan open, overdue, atau duedate
                if (!in_array($status, [
                    ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                    ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                    ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT
                ])) {
                    // Jika status produk bukan open, overdue, atau duedate, set status ke Awaiting
                    $status = ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;
                } else {
                    // Jika status produk valid, periksa status berdasarkan due_date dan tab_spb
                    $dueDateDayYear = $dueDate->format('d-Y'); // Format tanggal hanya hari dan tahun
                    $nowDateDayYear = $nowDate->format('d-Y'); // Tanggal sekarang (hari dan tahun)

                    // Periksa status berdasarkan due_date dan tab_spb hanya jika status belum "Awaiting"
                    if ($status !== ProductCompanySpbProject::TEXT_AWAITING_PRODUCT) {
                        if ($spbProject->tab_spb == SpbProject::TAB_VERIFIED || $spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
                            if ($nowDateDayYear > $dueDateDayYear) {
                                // Jika tanggal sekarang lebih besar dari due_date (terlambat), set status ke OVERDUE
                                $status = ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;
                            } elseif ($nowDateDayYear == $dueDateDayYear) {
                                // Jika tanggal sekarang sama dengan due_date (tepat waktu), set status ke DUEDATE
                                $status = ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;
                            } elseif ($nowDateDayYear < $dueDateDayYear) {
                                // Jika tanggal sekarang lebih kecil dari due_date (belum lewat), set status ke OPEN
                                $status = ProductCompanySpbProject::TEXT_OPEN_PRODUCT;
                            }
                        }
                    }
                } */

                // Menangani status "Rejected" jika tidak ditemukan sebelumnya
                if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    $noteReject = $product->note_reject_produk;
                } else {
                    $noteReject = null;
                }

                return [
                    'produk_data' => [
                        'produk_id' => $product->produk_id ?? null,
                        'nama' => $product->product->nama ?? null,
                        'id_kategori' => $product->product->id_kategori ?? null,
                        'type_pembelian' => $product->product->type_pembelian ?? null,
                        'harga_product' => $product->product->harga_product ?? null,
                    ],
                    'vendor' => [
                        'id' => $product->company->id ?? 'Unknown',
                        'name' => $product->company->name ?? 'Unknown',
                        'bank_name' => $product->company->bank_name ?? 'Unknown',
                        'account_name' => $product->company->account_name ?? 'Unknown',
                    ],
                    'total_vendor' => $product->getTotalVendorAttribute(),
                    'status_vendor' => $status,
                    'status_produk' => $status,
                    'note_reject_produk' => $noteReject,
                'date' => $product->date,
                'due_date' => $product->due_date,
                'description' => $product->description,
                'ppn' => $product->ppn_detail, 
                'ongkir' => $product->ongkir ?? 0,
                'harga' => $product->harga ?? 0,
                'stok' => $product->stok ?? 0,
                'subtotal_item' => $product->subtotal_produk,
                'payment_date' => $product->payment_date ?? null,  
                'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                /* 'pph' => [
                    'pph_type' => $product->taxPph->name ?? 'Unknown',
                    'pph_rate' => $product->taxPph->percent ?? 0,
                    'pph_hasil' => $product->pph_value,
                ], */
                // 'total_item' => $product->total_produk,
            ];
        }),
            "total" => $spbProject->total_produk,
            'sisa_pembayaran' => $spbProject->sisaPembayaranProductVendor(),
            'total_terbayarkan' => $spbProject->totalTerbayarProductVendor(),
            'file_attachement' => $this->getDocument($spbProject),
            "unit_kerja" => $spbProject->unit_kerja,
            "tanggal_dibuat_spb" => $spbProject->tanggal_dibuat_spb,
            "tanggal_berahir_spb" => $spbProject->tanggal_berahir_spb,
            "harga_total_pembayaran_borongan_spb" => $spbProject->harga_total_pembayaran_borongan_spb ?? null,
            "vendor_borongan" => $company ? [
                    "id" => $company->id,
                    "name" => $company->name,
                    "bank_name" => $company->bank_name,
                    "account_name" => $company->account_name,
                ] : null,
            'sisa_pembayaran_termin_spb' => $this->getDataSisaPemabayaranTerminSpb($spbProject),
            "harga_total_termin_spb" => $this->getHargaTerminSpb($spbProject),
            "deskripsi_termin_spb" => $spbProject->deskripsi_termin_spb ?? null,
            "type_termin_spb" => $this->getDataTypetermin($spbProject->type_termin_spb),
            "riwayat_termin" => $this->getRiwayatTermin($spbProject),
            "know_spb_marketing" => $this->getUserRole($spbProject->know_marketing),
            "know_spb_supervisor" => $this->getUserRole($spbProject->know_supervisor),
            "know_spb_kepalagudang" => $this->getUserRole($spbProject->know_kepalagudang),
            "accept_spb_finance" => $this->getUserRole($spbProject->know_finance), 
            "payment_request_owner" => auth()->user()->hasRole(Role::OWNER) || $spbProject->request_owner ? $this->getUserRole($spbProject->request_owner) : null,
            "created_at" => $spbProject->created_at->format('Y-m-d'),
            // "updated_at" => $spbProject->updated_at->format('Y-m-d'),
            "updated_at" =>  $this->getUpdatedAt($spbProject),
        ];

        // Add created_by if user is associated
        if ($spbProject->user) {
            $data['created_by'] = [
                "id" => $spbProject->user->id,
                "name" => $spbProject->user->name,
                "created_at" => Carbon::parse($spbProject->created_at)->timezone('Asia/Jakarta')->toDateTimeString(),
            ];
        }    

        // Kembalikan data dalam format yang sudah ditentukan
        return MessageActeeve::render($data);
    }

    protected function getUpdatedAt($spbProject)
    {
        // Pastikan relasi category() dimuat dengan benar
        $category = $spbProject->category;

        if (!$category) {
            return "-"; // Jika tidak ada kategori, kembalikan default "-"
        }

        // Ambil total harga termin yang sudah dibayar
        $hargaTotalTermin = $this->getHargaTerminSpb($spbProject);

        // Cek apakah ada vendor yang sudah paid pada kategori Invoice atau FlashCash
        $isPaidVendor = $spbProject->productCompanySpbprojects->contains(function ($product) {
            return $product->status_vendor === ProductCompanySpbProject::TEXT_PAID_PRODUCT;
        });

        // ✅ **Jika kategori SPB adalah Borongan**
        if ($category->id == SpbProjectCategory::BORONGAN) {
            // 🔹 Jika belum ada pembayaran (harga termin masih 0)
            if ($hargaTotalTermin == 0) {
                return "Belum Ada Pembayaran";
            }

            // 🔹 Jika total pembayaran borongan masih belum lunas DAN belum mencapai TAB_PAID
            if ($hargaTotalTermin >= 0 && $spbProject->tab_spb != SpbProject::TAB_PAID) {
                return "Pembayaran Sudah Sebagian";
            }

            // 🔹 Jika sudah mencapai TAB_PAID, langsung tampilkan tanggal updated_at
            return $spbProject->updated_at->format('Y-m-d');
        }

        // ✅ **Jika kategori SPB adalah Invoice atau FlashCash**
        if (in_array($category->id, [SpbProjectCategory::INVOICE, SpbProjectCategory::FLASH_CASH])) {
            // 🔹 Jika tidak ada vendor dengan status PAID
            if (!$isPaidVendor) {
                return "Belum Ada Pembayaran";
            }

            // 🔹 Jika ada vendor yang sudah PAID tetapi belum mencapai TAB_PAID
            if ($isPaidVendor && $spbProject->tab_spb != SpbProject::TAB_PAID) {
                return "Pembayaran Sudah Sebagian";
            }

            // 🔹 Jika sudah mencapai TAB_PAID, tampilkan tanggal updated_at
            return $spbProject->updated_at->format('Y-m-d');
        }

        return "-"; // Default jika kategori tidak sesuai
    }

    protected function getDataSisaPemabayaranTerminSpb($spbProject)
    {
        // Ambil total harga termin yang sudah dibayar (total harga termin yang ada di SPB project)
        $totalHargaTermin = $this->getHargaTerminSpb($spbProject);
    
        // Pastikan harga_total_pembayaran_borongan_spb dalam bentuk angka
        $hargaPembayaranBorongan = floatval($spbProject->harga_total_pembayaran_borongan_spb ?? 0);
    
        // Jika belum ada pembayaran termin (totalHargaTermin adalah 0), kembalikan harga total borongan SPB
        if ($totalHargaTermin == 0) {
            return $hargaPembayaranBorongan; // Mengembalikan harga borongan SPB sebagai angka
        }
    
        // Jika sudah ada pembayaran termin, hitung sisa pembayaran
        $sisaPembayaran = $hargaPembayaranBorongan - $totalHargaTermin;
    
        return $sisaPembayaran; // Mengembalikan sisa pembayaran dalam bentuk angka
    }

    protected function getHargaTerminSpb($spbProject)
    {
        return $spbProject->harga_termin_spb ?? 0;
    }

    protected function getRiwayatTermin($spbProject)
    {
        return $spbProject->termins->map(function ($termin) use ($spbProject) {
            return [
                'id' => $termin->id, 
                'harga_termin' => $termin->harga_termin,
                'deskripsi_termin' => $termin->deskripsi_termin,
                'type_termin_spb' => $this->getDataTypetermin($termin->type_termin_spb),
                'tanggal' => $termin->tanggal,
                'file_attachment' => $termin->fileAttachment ? [
                    'id' => $termin->fileAttachment->id,
                    'name' => $termin->fileAttachment->spbProject->doc_type_spb . "/{$termin->fileAttachment->doc_no_spb}.{$termin->fileAttachment->id}/" . date('Y', strtotime($termin->fileAttachment->created_at)) . "." . pathinfo($termin->fileAttachment->file_path, PATHINFO_EXTENSION),
                    'link' => asset("storage/{$termin->fileAttachment->file_path}"),
                ] : null,
            ];
        });
    }

    protected function getDataTypetermin($status) {
        $statuses = [

            SpbProject::TYPE_TERMIN_BELUM_LUNAS => "Belum Lunas",
            SpbProject::TYPE_TERMIN_LUNAS => "Lunas",
         ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
    }

    protected function getDocument($documents)
    {
        $data = [];

        // Pastikan menggunakan relasi yang benar, dalam hal ini 'documents_spb'
        foreach ($documents->documents as $document) {
            $data[] = [
                "id" => $document->id,
                "name" => $document->spbProject->doc_type_spb . "/$document->doc_no_spb.$document->id/" . date('Y', strtotime($document->created_at)) . "." . pathinfo($document->file_path, PATHINFO_EXTENSION),
                "link" => asset("storage/$document->file_path"),
            ];
        }

        return $data;
    }

    /* Validasi Data Vendors Dan Produk */
    private function getVendorsWithProducts(SpbProject $spbProject)
    {
        // Ambil data produk yang terkait dengan spb_project_id tertentu
        $produkRelated = DB::table('product_company_spbproject')
                            ->where('spb_project_id', $spbProject->doc_no_spb)
                            ->get();

        // Ambil produk berdasarkan vendor_id dan relasikan dengan produk yang terkait
        return is_iterable($spbProject->vendors)
            ? $spbProject->vendors->sortBy('id')
                ->groupBy('id')  // Mengelompokkan vendor berdasarkan id
                ->map(function ($vendors) use ($spbProject, $produkRelated) {
                    // Ambil vendor pertama dalam kelompok
                    $vendor = $vendors->first();

                    // Ambil nama perusahaan berdasarkan vendor_id (company_id)
                    $company = DB::table('companies')->where('id', $vendor->id)->first();
                    $companyName = $company ? $company->name : 'Unknown';
                    $companyBankName = $company ? $company->bank_name : 'Unknown';
                    $companyAccountNumber = $company ? $company->account_number : 'Unknown';

                    // Filter produk yang sesuai dengan company_id vendor
                    $produkData = $produkRelated->where('company_id', $vendor->id)
                        ->map(function ($produk) {
                            // Ambil detail produk berdasarkan produk_id
                            $product = DB::table('products')->find($produk->produk_id);
                            return [
                                'produk_id' => $product->id,
                                'produk_data' => [
                                    'nama' => $product->nama,
                                    'id_kategori' => $product->id_kategori,
                                    'deskripsi' => $product->deskripsi,
                                    'harga' => $product->harga,
                                    'stok' => $product->stok,
                                    'type_pembelian' => $product->type_pembelian
                                ]
                            ];
                        });

                    // Ambil ongkir hanya sekali per vendor (menghindari duplikasi ongkir)
                    $ongkir = $produkRelated->where('company_id', $vendor->id)->pluck('ongkir')->first();

                    // Menghindari duplikasi produk dalam vendor
                    return [
                        "vendor_id" => $vendor->id,
                        "company_name" => $companyName, 
                        "bank_toko_vendor" => $companyBankName,
                        "account_number_toko_vendor" => $companyAccountNumber, 
                        "ongkir" => $ongkir,  // Menampilkan ongkir hanya satu kali untuk vendor
                        "produk" => $this->removeDuplicatesByProductId($produkData->toArray())
                    ];
                })
                ->values()  // Mengubah array menjadi numerik tanpa key angka
            : [];
    }

    /**
    * Fungsi untuk menghapus duplikasi produk berdasarkan produk_id
    */
    private function removeDuplicatesByProductId(array $produkData)
    {
        $seen = [];
        $result = [];

        foreach ($produkData as $produk) {
            if (!in_array($produk['produk_id'], $seen)) {
                $seen[] = $produk['produk_id'];
                $result[] = $produk;
            }
        }

        return $result;
    }

    protected function getPpn($spbProject)
    {
        if (is_numeric($spbProject->ppn) && $spbProject->ppn > 0) {
            return ($spbProject->getSubtotal() * $spbProject->ppn) / 100;
        } else {
            return 0;
        }
    }

    protected function getPph($spbProject)
    {
        if (is_numeric($spbProject->pph)) {
            // Hitung hasil PPH berdasarkan nilai PPH dan subtotal
            $pphResult = round((($spbProject->getSubtotal()) * $spbProject->taxPph->percent) / 100);

            // Mengembalikan hasil PPH dalam format yang sesuai
            return [
                "pph_type" => $spbProject->taxPph->name,
                "pph_rate" => $spbProject->taxPph->percent,
                "pph_hasil" => $pphResult
            ];
        } else {
            return [
                "pph_type" => "", // Atau nilai default lainnya jika pph bukan numerik
                "pph_rate" => 0,
                "pph_hasil" => 0
            ];
        }
    }

    /**
     * Get the role_name of the user based on user ID.
     *
     * @param int|null $userId
     * @return array|null
     */
    protected function getUserRole($userId)
    {
        if ($userId) {
            // Ambil data pengguna berdasarkan user_id
            $user = \App\Models\User::find($userId);
    
            if ($user) {
                // Ambil approve_date langsung dari tabel spb_projects untuk user terkait
                $approveDate = DB::table('spb_projects')
                    ->where(function ($query) use ($userId) {
                        $query->where('know_marketing', $userId)
                              ->orWhere('know_supervisor', $userId)
                              ->orWhere('know_kepalagudang', $userId)
                              ->orWhere('know_finance', $userId)
                              ->orWhere('request_owner', $userId);
                    })
                    ->orderByDesc('approve_date')
                    ->value('approve_date'); // Ambil nilai approve_date
    
                // Ubah waktu approve_date ke timezone Jakarta
                $formattedApproveDate = $approveDate 
                    ? \Carbon\Carbon::parse($approveDate)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')
                    : 'Not approved yet';
    
                return [
                    'user_name' => $user->name,
                    'role_name' => $user->role ? $user->role->role_name : 'Unknown',
                    'approve_date' => $formattedApproveDate,
                ];
            }
        }
        return null;
    }


    /**
     * Get the status of the SPB Project.
     *
     * @param SpbProject $spbProject
     * @return array
     */
    protected function getStatus($spbProject)
    {
        $data = [];

        // Nama tab berdasarkan konstanta yang ada di model SpbProject
        $tabNames = [
            SpbProject::TAB_SUBMIT => 'Submit',
            SpbProject::TAB_VERIFIED => 'Verified',
            SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
            SpbProject::TAB_PAID => 'Paid',
        ];

        // Ambil nama tab berdasarkan nilai tab
        $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';  // Default jika tidak ditemukan

        // Pengecekan status yang berkaitan dengan TAB_SUBMIT
        if ($spbProject->tab_spb == SpbProject::TAB_SUBMIT) {
            // Pastikan status ada, jika tidak set default ke AWAITING
            if ($spbProject->status) {
                $data = [
                    "id" => $spbProject->status->id ?? SpbProject_Status::AWAITING,
                    "name" => $spbProject->status->name ?? SpbProject_Status::TEXT_AWAITING,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];

                // Jika status adalah REJECTED, tambahkan note
                if ($spbProject->status->id == SpbProject_Status::REJECTED) {
                    $data["reject_note"] = $spbProject->reject_note ?? 'No reject note';
                }
            } else {
                // Jika status tidak ada, set ke AWAITING
                $data = [
                    "id" => SpbProject_Status::AWAITING,
                    "name" => SpbProject_Status::TEXT_AWAITING,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAID
        elseif ($spbProject->tab_spb == SpbProject::TAB_PAID) {
            $data = [
                "id" => $spbProject->status->id ?? null,
                "name" => $spbProject->status ? $spbProject->status->name : 'Unknown',
                "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];
        }

        // Pengecekan untuk TAB_VERIFIED
        elseif ($spbProject->tab_spb == SpbProject::TAB_VERIFIED) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $data = [
                "id" => SpbProject_Status::OPEN,
                "name" => SpbProject_Status::TEXT_OPEN,
                "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => SpbProject_Status::OVERDUE,
                    "name" => SpbProject_Status::TEXT_OVERDUE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                $data = [
                    "id" => SpbProject_Status::DUEDATE,
                    "name" => SpbProject_Status::TEXT_DUEDATE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAYMENT_REQUEST
        elseif ($spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
            // Pastikan jika statusnya REJECTED, maka status akan diubah menjadi REJECTED dalam respons
            if ($spbProject->status->id == SpbProject_Status::REJECTED) {
                $data = [
                    "id" => SpbProject_Status::REJECTED,
                    "name" => 'Rejected',
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                    "reject_note" => $spbProject->reject_note ?? 'No reject note',  // Menambahkan catatan reject
                ];
            } else {
                // Jika status lainnya, tetap ikuti kondisi sebelumnya
                $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
                $nowDate = Carbon::now();

                $data = [
                    "id" => SpbProject_Status::OPEN,
                    "name" => SpbProject_Status::TEXT_OPEN,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];

                if ($nowDate->gt($dueDate)) {
                    $data = [
                        "id" => SpbProject_Status::OVERDUE,
                        "name" => SpbProject_Status::TEXT_OVERDUE,
                        "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                    ];
                }

                if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                    $data = [
                        "id" => SpbProject_Status::DUEDATE,
                        "name" => SpbProject_Status::TEXT_DUEDATE,
                        "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                    ];
                }
            }
        }

        // Kembalikan data status yang sesuai dengan tab
        return $data;
    }

    public function accSpbProject(Request $request, $docNoSpb)
    {
        DB::beginTransaction();

        // Cari SPB Project berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('SPB Project not found!');
        }

        try {
            // Pesan dasar untuk status perubahan
            $message = "";

            // Periksa role user yang sedang login dan lakukan pembaruan status yang sesuai
            switch (auth()->user()->role_id) {
                case Role::MARKETING:
                    // Update kolom know_marketing jika user adalah Marketing
                    $spbProject->update([
                        'know_marketing' => auth()->user()->id,
                        'approve_date' => now(),
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Marketing.";
                    break;

                case Role::GUDANG:
                    // Update kolom know_kepalagudang jika user adalah Kepala Gudang
                    $spbProject->update([
                        'know_kepalagudang' => auth()->user()->id,
                        'approve_date' => now(),
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Gudang.";
                    break;

                case Role::FINANCE:
                    // Update kolom know_finance jika user adalah Finance
                    $spbProject->update([
                        'know_finance' => auth()->user()->id,
                        'approve_date' => now(),
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Finance.";
                    break;

                case Role::SUPERVISOR:
                    // Update kolom know_supervisor jika user adalah Supervisor
                    $spbProject->update([
                        'know_supervisor' => auth()->user()->id,
                        'approve_date' => now(),
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Supervisor.";
                    break;

                case Role::OWNER:
                    // Update kolom request_owner jika user adalah Owner
                    $spbProject->update([
                        'request_owner' => auth()->user()->id,
                        'approve_date' => now(),
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now Accepted by Owner.";
                    break;

                default:
                    // Jika role tidak valid, kirimkan error
                    return MessageActeeve::error('Access denied. You do not have permission to perform this action.');
            }

            // Commit transaksi
            DB::commit();

            // Dapatkan informasi siapa yang terakhir menyetujui
            $lastApprovedByMarketing = $this->getUserRole($spbProject->know_marketing);
            $lastApprovedBySupervisor = $this->getUserRole($spbProject->know_supervisor);
            $lastApprovedByGudang = $this->getUserRole($spbProject->know_kepalagudang);
            $lastApprovedByFinance = $this->getUserRole($spbProject->know_finance);
            $lastApprovedByOwner = $this->getUserRole($spbProject->request_owner);

            // Buat pesan tambahan berdasarkan status terakhir
            $logMessage = [
                "know_marketing" => $lastApprovedByMarketing
                    ? "Last Marketing acknowledgment by {$lastApprovedByMarketing['user_name']} ({$lastApprovedByMarketing['role_name']})"
                    : "Marketing has not acknowledged yet.",
                "know_supervisor" => $lastApprovedBySupervisor
                    ? "Last Supervisor acknowledgment by {$lastApprovedBySupervisor['user_name']} ({$lastApprovedBySupervisor['role_name']})"
                    : "Supervisor has not acknowledged yet.",
                "know_kepalagudang" => $lastApprovedByGudang
                    ? "Last Gudang acknowledgment by {$lastApprovedByGudang['user_name']} ({$lastApprovedByGudang['role_name']})"
                    : "Gudang has not acknowledged yet.",
                "know_finance" => $lastApprovedByFinance
                    ? "Last Finance acknowledgment by {$lastApprovedByFinance['user_name']} ({$lastApprovedByFinance['role_name']})"
                    : "Finance has not acknowledged yet.",
                "request_owner" => $lastApprovedByOwner
                    ? "Last Owner acceptance by {$lastApprovedByOwner['user_name']} ({$lastApprovedByOwner['role_name']})"
                    : "Owner has not accepted yet.",
                "approve_date" => $spbProject->approve_date
                    ? "Last approval date: {$spbProject->approve_date}"
                    : "Approval date not set yet.",
            ];

            // Mengembalikan response sukses dengan pesan tambahan
            return MessageActeeve::success($message, ['logs' => $logMessage]);

        } catch (\Throwable $th) {
            // Jika ada error, rollback transaksi
            DB::rollBack();
            return MessageActeeve::error('An error occurred: ' . $th->getMessage());
        }
    }

    /* public function deleteTermin(Request $request, $docNoSpb)
    {
        DB::beginTransaction();

        try {
            // Validasi input JSON
            if (!$request->has('riwayat_termin') || !is_array($request->riwayat_termin)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON format. "riwayat_termin" must be an array of IDs.',
                ], 400);
            }

            // Ambil ID termin yang akan dihapus
            $terminIdsToDelete = $request->riwayat_termin;

            // Cari termin yang sesuai dengan ID yang diberikan
            $terminsToDelete = DB::table('spb_project_termins')
                ->where('doc_no_spb', $docNoSpb)
                ->whereIn('id', $terminIdsToDelete)
                ->get();

            if ($terminsToDelete->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid termin IDs found for deletion!',
                ], 404);
            }

            // Cek apakah ada termin dengan type_termin_spb == TYPE_TERMIN_LUNAS
            $cannotDeleteTermin = $terminsToDelete->firstWhere('type_termin_spb', SpbProject::TYPE_TERMIN_LUNAS);

            if ($cannotDeleteTermin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more termin(s) cannot be deleted because they are already marked as Lunas.',
                ], 400);
            }

            // Loop untuk menghapus setiap termin
            foreach ($terminsToDelete as $termin) {
                // Hapus file attachment jika ada
                if (!empty($termin->file_attachment_id)) {
                    $file = DB::table('document_spb')->where('id', $termin->file_attachment_id)->first();
                    if ($file) {
                        if (Storage::disk('public')->exists($file->file_path)) {
                            Storage::disk('public')->delete($file->file_path);
                        }
                        DB::table('document_spb')->where('id', $termin->file_attachment_id)->delete();
                    }
                }

                // Hapus termin dari database
                DB::table('spb_project_termins')->where('id', $termin->id)->delete();

               
            }

            // Hitung ulang total harga termin
            $totalHargaTermin = DB::table('spb_project_termins')
                ->where('doc_no_spb', $docNoSpb)
                ->sum(DB::raw('CAST(harga_termin AS UNSIGNED)')); // Pastikan tipe data

            // Jika tidak ada termin yang tersisa
            if ($totalHargaTermin == 0) {
                DB::table('spb_projects')->where('doc_no_spb', $docNoSpb)->update([
                    'deskripsi_termin_spb' => null,
                    'type_termin_spb' => null,
                    'harga_termin_spb' => 0,
                ]);
            } else {
                // Jika masih ada termin, ambil termin terakhir untuk update deskripsi dan type
                $lastTermin = DB::table('spb_project_termins')
                    ->where('doc_no_spb', $docNoSpb)
                    ->orderBy('id', 'desc')
                    ->first();

                DB::table('spb_projects')->where('doc_no_spb', $docNoSpb)->update([
                    'deskripsi_termin_spb' => $lastTermin->deskripsi_termin,
                    'type_termin_spb' => $lastTermin->type_termin_spb,
                    'harga_termin_spb' => $totalHargaTermin,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Selected termin(s) deleted successfully!',
                'remaining_total_termin' => $totalHargaTermin,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error during termin deletion: ' . $th->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    } */

    public function deleteTermin(Request $request, $docNoSpb)
    {
        DB::beginTransaction();

        try {
            /* -----------------------------------------------------------
            * 1. Validasi payload
            * ----------------------------------------------------------*/
            $terminIds = $request->input('riwayat_termin');
            if (!is_array($terminIds) || empty($terminIds)) {
                return response()->json([
                    'status'  => 'ERROR',
                    'message' => '"riwayat_termin" harus berupa array ID termin',
                ], 400);
            }

            /* -----------------------------------------------------------
            * 2. Ambil SPB + termin yang dipilih
            * ----------------------------------------------------------*/
            /** @var \App\Models\SpbProject $spb */
            $spb = SpbProject::where('doc_no_spb', $docNoSpb)->lockForUpdate()->firstOrFail();

            $termins = $spb->termins()
                ->whereIn('id', $terminIds)
                ->get();

            if ($termins->isEmpty()) {
                return response()->json([
                    'status'  => 'ERROR',
                    'message' => 'ID termin tidak ditemukan pada SPB ini',
                ], 404);
            }

            /* -----------------------------------------------------------
            * 3. Hapus termin + file attachment
            * ----------------------------------------------------------*/
            foreach ($termins as $t) {
                // hapus file pada document_spb (jika ada)
                if ($t->file_attachment_id) {
                    $doc = DB::table('document_spb')->where('id', $t->file_attachment_id)->first();
                    if ($doc && Storage::disk('public')->exists($doc->file_path)) {
                        Storage::disk('public')->delete($doc->file_path);
                    }
                    DB::table('document_spb')->where('id', $t->file_attachment_id)->delete();
                }

                $t->delete();
            }

            /* -----------------------------------------------------------
            * 4. Hitung ulang termin yang tersisa
            * ----------------------------------------------------------*/
            $remaining = $spb->termins()
                ->orderBy('tanggal', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $totalTermin = $remaining->sum(fn ($row) => (int) $row->harga_termin);
            $latest      = $remaining->first();                // bisa null
            $billing     = (float) $spb->harga_total_pembayaran_borongan_spb;
            $isLunas     = $billing > 0 && $totalTermin >= $billing;

            /* -----------------------------------------------------------
            * 4B. Normalisasi STATUS setiap termin
            * ----------------------------------------------------------*/
            if ($isLunas) {
                // semua → 1, lalu terbaru → 2
                $spb->termins()->update([
                    'type_termin_spb' => SpbProject::TYPE_TERMIN_BELUM_LUNAS,
                ]);
                if ($latest) {
                    $latest->update(['type_termin_spb' => SpbProject::TYPE_TERMIN_LUNAS]);
                }
            } else {
                // pastikan tak ada yang 2
                $spb->termins()
                    ->where('type_termin_spb', SpbProject::TYPE_TERMIN_LUNAS)
                    ->update(['type_termin_spb' => SpbProject::TYPE_TERMIN_BELUM_LUNAS]);
            }

            /* -----------------------------------------------------------
            * 5.  Update kolom ringkasan SPB
            * ----------------------------------------------------------*/
            $currentType = $isLunas
                ? SpbProject::TYPE_TERMIN_LUNAS          // 2
                : SpbProject::TYPE_TERMIN_BELUM_LUNAS;   // 1

            $spb->update([
                'deskripsi_termin_spb' => $latest?->deskripsi_termin,
                'type_termin_spb'      => $currentType,          // ← gunakan status baru
                'harga_termin_spb'     => $totalTermin,
            ]);

            DB::commit();

            return response()->json([
                'status'                 => 'SUCCESS',
                'message'                => 'Selected termin(s) deleted successfully!',
                'remaining_total_termin' => $totalTermin,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error during SPB termin deletion: '.$th->getMessage());

            return response()->json([
                'status'  => 'ERROR',
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function updateTermin(UpdateTerminRequest $request, $docNo)
    {
        DB::beginTransaction();
        
        try {
            // Cari SPB Project berdasarkan doc_no_spb
            $spbProject = SpbProject::with(['termins', 'termins.fileAttachment'])->where('doc_no_spb', $docNo)->first();
    
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Variabel untuk menyimpan data termin terakhir yang diupdate
            $lastUpdatedTerminData = null;
    
            // Proses pembaruan termin berdasarkan ID termin yang diterima
            foreach ($request->riwayat_termin as $index => $terminData) {
                $termin = $spbProject->termins->where('id', $terminData['id'])->first();
    
                if (!$termin) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Termin with ID {$terminData['id']} not found!",
                    ], 404);
                }
    
                // Inisialisasi variabel untuk menyimpan file attachment
                $fileAttachmentId = null;
    
                // Cek apakah ada file yang diupload untuk termin ini
                if (isset($terminData['attachment_file_spb']) && !empty($terminData['attachment_file_spb'])) {
                    foreach ($terminData['attachment_file_spb'] as $file) {
                        if ($file && $file->isValid()) {
                            // Cek apakah file attachment sudah ada, jika ada maka replace
                            if ($termin->fileAttachment) {
                                // Ganti file yang lama dengan file yang baru
                                $document = $this->replaceDocuments($termin->fileAttachment, $file, $index);
                                $fileAttachmentId = $document->id;  // Simpan ID file attachment yang baru
                            } else {
                                // Jika belum ada file attachment, simpan file baru
                                $document = $this->saveDocument($spbProject, $file, $index);
                                $fileAttachmentId = $document->id;  // Simpan ID file attachment yang baru
                            }
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'File upload failed',
                            ], 400);
                        }
                    }
                } else {
                    // Jika tidak ada file yang diupload, hapus file attachment yang lama
                    if ($termin->fileAttachment) {
                        // Hapus file fisik yang terkait
                        if (Storage::disk('public')->exists($termin->fileAttachment->file_path)) {
                            Storage::disk('public')->delete($termin->fileAttachment->file_path); // Menghapus file fisik
                        }
    
                        // Hapus record file attachment dari database
                        $termin->fileAttachment->delete();
    
                        // Pastikan file_attachment_id di termin menjadi null
                        $termin->update([
                            'file_attachment_id' => null,
                        ]);
                    }
                }
    
                // Update data termin dengan file_attachment_id yang baru (atau null jika tidak ada file)
                $termin->update([
                    'harga_termin' => $terminData['harga_termin'],
                    'deskripsi_termin' => $terminData['deskripsi_termin'],
                    'type_termin_spb' => $terminData['type_termin_spb'], // Langsung pakai ID
                    'tanggal' => $terminData['tanggal'],
                    'file_attachment_id' => $fileAttachmentId,  // null jika tidak ada file
                ]);
    
                $spbProject->load('termins.fileAttachment');
    
                // Simpan data termin terakhir yang diupdate
                $lastUpdatedTerminData = $terminData;
            }
    
            // Jika ada termin yang diupdate, perbarui deskripsi dan tipe termin SPB
            /* if ($lastUpdatedTerminData) {
                $spbProject->update([
                    'deskripsi_termin_spb' => $lastUpdatedTerminData['deskripsi_termin'],
                    'type_termin_spb' => $lastUpdatedTerminData['type_termin_spb'], 
                ]);
            } */

            if ($lastUpdatedTerminData) {
                $updateFields = [
                    'deskripsi_termin_spb' => $lastUpdatedTerminData['deskripsi_termin'],
                    'type_termin_spb' => $lastUpdatedTerminData['type_termin_spb'], // ID Type Termin
                ];
    
                // Jika type_termin_spb adalah LUNAS, pindahkan ke TAB PAID
                if ($lastUpdatedTerminData['type_termin_spb'] == SpbProject::TYPE_TERMIN_LUNAS) {
                    $updateFields['spbproject_status_id'] = SpbProject_Status::PAID;
                    $updateFields['tab_spb'] = SpbProject::TAB_PAID;
                } else {
                    $updateFields['tab_spb'] = SpbProject::TAB_PAYMENT_REQUEST;
                }
    
                // Update SPB Project
                $spbProject->update($updateFields);
            }
    
            // Menghitung ulang harga total termin setelah update termin
            $totalHargaTermin = $spbProject->termins->sum('harga_termin');
           /*  $spbProject->harga_total_pembayaran_borongan_spb = $spbProject->harga_total_pembayaran_borongan_spb - $totalHargaTermin; */
            $spbProject->update([
                'harga_termin_spb' => $totalHargaTermin,
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Termin data updated successfully!',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    protected function replaceDocuments($existingDocument, $file, $iteration)
    {
        // Hapus file fisik yang lama
        if (Storage::disk('public')->exists($existingDocument->file_path)) {
            Storage::disk('public')->delete($existingDocument->file_path); // Menghapus file fisik
        }

        // Hapus record file attachment yang lama dari database
        $existingDocument->delete();

        // Simpan file baru dan kembalikan ID-nya
        return $this->saveDocument($existingDocument->spbProject, $file, $iteration);
    }

    public function acceptproduk(AcceptRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects.taxPph'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

            // Validasi apakah user memiliki peran Finance
            /* if (!auth()->user()->hasRole(Role::FINANCE)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only users with the Finance role can accept products.',
                ], 403);
            } */

            if (!auth()->user()->hasRole(Role::FINANCE) && !auth()->user()->hasRole(Role::OWNER) && !auth()->user()->hasRole(Role::ADMIN)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only users with the Finance, Owner, or Admin role can accept this SPB.',
                ], 403);
            }

            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
                $pphId = $produkData['pph_id'] ?? null;

                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();

                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }

                // Validasi dan update PPH
               /*  if ($pphId) {
                    $pph = Tax::find($pphId);
                    if (!$pph || strtolower($pph->type) != Tax::TAX_PPH) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "PPH ID {$pphId} is invalid or not a PPH type.",
                        ], 400);
                    }
                } */

                // Tentukan status produk berdasarkan due_date
                $dueDate = Carbon::parse($product->due_date); // Pastikan due_date dalam format tanggal yang valid
                $nowDate = Carbon::now();

                $status = $product->status_produk;
                $status = $product->status_vendor;

                // Logika pembaruan status berdasarkan due_date
                if ($status !== ProductCompanySpbProject::TEXT_PAID_PRODUCT && $status !== ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    if ($nowDate->isSameDay($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;
                    } elseif ($nowDate->gt($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;
                    } elseif ($nowDate->lt($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_OPEN_PRODUCT;
                    }
                }

                // Perbarui PPH dan status produk
                $product->update([
                    // 'pph' => $pphId, 
                    'status_produk' => $status, 
                    'status_vendor' => $status, 
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been accepted by Finance.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function accept($id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

             
            if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN) {
                // Jika kategori BORONGAN, hanya perlu persetujuan request_owner
                if (!$spbProject->request_owner) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot accept SPB Project. Request owner approval is required for BORONGAN category.',
                    ], 400);
                }
            } else {
                // Jika kategori lain, semua persetujuan harus diisi
                /* if (!$spbProject->know_kepalagudang || !$spbProject->know_supervisor) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot accept SPB Project. All approvals must be completed first.',
                    ], 400);
                } */
                if ($spbProject->spbproject_category_id == SpbProject_Category::INVOICE) {
                    if (!$spbProject->know_kepalagudang || !$spbProject->know_supervisor) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Cannot accept SPB Project. All approvals must be completed first.',
                        ], 400);
                    }
                }
            }

             // Validasi apakah user memiliki peran Finance, Owner, atau Admin
            if (!auth()->user()->hasRole(Role::FINANCE) && !auth()->user()->hasRole(Role::OWNER) && !auth()->user()->hasRole(Role::ADMIN)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only users with the Finance, Owner, or Admin role can accept this SPB.',
                ], 403);
            }

            // Periksa tanggal berakhir SPB
            /* $dueDate = Carbon::parse($spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $spbStatus = null;

            // Logika pembaruan status berdasarkan tanggal akhir SPB
            if ($nowDate->isSameDay($dueDate)) {
                $spbStatus = SpbProject_Status::DUEDATE; 
            } elseif ($nowDate->gt($dueDate)) {
                $spbStatus = SpbProject_Status::OVERDUE; 
            } elseif ($nowDate->lt($dueDate)) {
                $spbStatus = SpbProject_Status::OPEN; 
            } */

            $dueDate = Carbon::parse($spbProject->tanggal_berahir_spb)->timezone('Asia/Jakarta');
            $nowDate = Carbon::now('Asia/Jakarta');  

            $spbStatus = null;

            // Logika pembaruan status berdasarkan tanggal akhir SPB
            if ($nowDate->isSameDay($dueDate)) {
                $spbStatus = SpbProject_Status::DUEDATE; 
            } elseif ($nowDate->gt($dueDate)) {
                $spbStatus = SpbProject_Status::OVERDUE; 
            } elseif ($nowDate->lt($dueDate)) {
                $spbStatus = SpbProject_Status::OPEN; 
            }


            // Iterasi semua produk yang terkait dengan SPB Project
            foreach ($spbProject->productCompanySpbprojects as $product) {
                $dueDate = Carbon::parse($product->due_date);
                $nowDate = Carbon::now();

                $status = $product->status_produk;
                $status = $product->status_vendor;

                // Logika pembaruan status berdasarkan due_date
                if ($status !== ProductCompanySpbProject::TEXT_PAID_PRODUCT && $status !== ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    if ($nowDate->isSameDay($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;
                    } elseif ($nowDate->gt($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;
                    } elseif ($nowDate->lt($dueDate)) {
                        $status = ProductCompanySpbProject::TEXT_OPEN_PRODUCT;
                    }
                }

                // Perbarui status produk
                $product->update([
                    'status_produk' => $status,
                    'status_vendor' => $status,
                ]);
            }

             // Cek kategori SPB, jika kategori BORONGAN, ubah tab_spb ke PAYMENT REQUEST setelah accept
            if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN) {
                $tabSpb = SpbProject::TAB_PAYMENT_REQUEST; // Ubah tab SPB ke Payment Request
            } else {
                $tabSpb = SpbProject::TAB_VERIFIED; // Jika bukan BORONGAN, tetap di VERIFIED
            }

            // Perbarui status, tab, dan know_finance untuk SPB Project
            $spbProject->update([
                'spbproject_status_id' => $spbStatus,
                'tab_spb' => $tabSpb,
                'know_finance' => auth()->user()->hasRole(Role::FINANCE) ? auth()->user()->id : null, // Tandai bahwa Finance telah menerima SPB
                'approve_date' => now(), // Waktu persetujuan
            ]);

            // Tambahkan log
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => $tabSpb,
                'name' => auth()->user()->name,
                'message' => "SPB Project {$spbProject->doc_no_spb} is now acknowledged by " . auth()->user()->name,
            ]);
            
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been accepted by " . auth()->user()->name,
                'new_status' => SpbProject_Status::getStatusText($spbStatus),
            ]);
            
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function paymentproduk(PaymentProdukRequest $request, $id)
    {
        DB::beginTransaction();
    
        try {
            if (!auth()->user()->hasRole(Role::FINANCE) && !auth()->user()->hasRole(Role::OWNER)) {
                return MessageActeeve::forbidden('Only users with the Finance or Owner role can update payments.');
            }

            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
    
                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();
    
                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }
    
                // Pastikan produk dalam status yang valid untuk diperbarui menjadi PAID
                $status = $product->status_produk;
                if (in_array($status, [
                    ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                    ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                    ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT,
                ])) {
                    // Perbarui status menjadi PAID
                    $product->update([
                        'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT, // Set status menjadi PAID
                        // 'note_paid_produk' => "Paid on " . Carbon::now()->format('Y-m-d H:i:s'), // Tambahkan catatan pembayaran
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} cannot be paid. Current status: {$status}",
                    ], 400);
                }
            }
    
           /*  // Tambahkan log
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => SpbProject::TAB_VERIFIED,
                'name' => auth()->user()->name,
                'message' => 'Products have been paid successfully.',
            ]); */
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "Payment request for products in SPB Project {$id} has been completed.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    
    public function undo($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::with('productCompanySpbprojects', 'termins')
        ->where('doc_no_spb', $docNoSpb)
        ->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        $userRole = auth()->user()->role_id;

        if ($spbProject->tab_spb == SpbProject::TAB_PAID && $userRole !== Role::OWNER) {
            return MessageActeeve::error("Unauthorized! Only OWNER can undo from PAID.");
        }

        try {
            // **Definisikan $nowDate sebelum digunakan**
        $nowDate = Carbon::now('Asia/Jakarta');

        // Cek apakah kategori SPB adalah BORONGAN
        if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN) {
            // **Jika berada di TAB_PAID, undo ke TAB_PAYMENT_REQUEST dulu**
            if ($spbProject->tab_spb == SpbProject::TAB_PAID) {
                $newTab = SpbProject::TAB_PAYMENT_REQUEST;
            } 
            // **Jika berada di TAB_PAYMENT_REQUEST, langsung ke TAB_SUBMIT**
            elseif ($spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
                $newTab = SpbProject::TAB_SUBMIT;
            } 
            // **Jaga-jaga jika ada skenario lain, default ke TAB_SUBMIT**
            else {
                $newTab = SpbProject::TAB_SUBMIT;
            }

            // Reset status produk di pivot table dengan ternary operator
            foreach ($spbProject->productCompanySpbprojects as $product) {
                $productDueDate = Carbon::parse($product->due_date)->timezone('Asia/Jakarta');

                $status = ($newTab == SpbProject::TAB_SUBMIT)
                    ? ProductCompanySpbProject::TEXT_AWAITING_PRODUCT
                    : ($nowDate->isSameDay($productDueDate)
                        ? ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT
                        : ($nowDate->gt($productDueDate)
                            ? ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT
                            : ProductCompanySpbProject::TEXT_OPEN_PRODUCT
                        )
                    );

                $product->update([
                    'status_produk' => $status,
                    'status_vendor' => $status,
                    'ppn' => 0,
                    'pph' => null,
                ]);
            }

            // **Reset harga termin & riwayat termin hanya jika dari TAB_PAID ke SUBMIT**
            if ($spbProject->tab_spb == SpbProject::TAB_PAID) {
                $spbProject->update([
                    'harga_termin_spb' => 0,
                    'deskripsi_termin_spb' => null,
                    'type_termin_spb' => SpbProject::TYPE_TERMIN_BELUM_LUNAS, // **Set ke "Belum Lunas" (1)**
                ]);

                // **Hapus semua riwayat termin**
                $spbProject->termins()->delete();
            }

            // **Update status SPB Project dan tab**
            $spbProject->update([
                'spbproject_status_id' => ($newTab == SpbProject::TAB_SUBMIT)
                    ? SpbProject_Status::AWAITING
                    : ($nowDate->isSameDay(Carbon::parse($spbProject->tanggal_berahir_spb))
                        ? SpbProject_Status::DUEDATE
                        : ($nowDate->gt(Carbon::parse($spbProject->tanggal_berahir_spb))
                            ? SpbProject_Status::OVERDUE
                            : SpbProject_Status::OPEN
                        )
                    ),
                'tab_spb' => $newTab,
            ]);
    
                // Tambahkan log undo untuk kategori BORONGAN
                LogsSPBProject::create([
                    'spb_project_id' => $spbProject->doc_no_spb,
                    'tab_spb' => $newTab,
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project with category BORONGAN has been undone and reverted to SUBMIT',
                ]);
    
                DB::commit();
    
                return MessageActeeve::success("SPB Project $docNoSpb has been undone successfully and reverted to SUBMIT for BORONGAN category");
            }
    
            // Jika bukan kategori BORONGAN, kurangi tab_spb satu tingkat
            $newTab = $spbProject->tab_spb - 1;
    
            // **Kondisikan status produk & vendor langsung di dalam update**
            foreach ($spbProject->productCompanySpbprojects as $product) {
                
                $productDueDate = Carbon::parse($product->due_date)->timezone('Asia/Jakarta');
                $nowDate = Carbon::now('Asia/Jakarta');

                // **Jika undo ke TAB_SUBMIT, semua produk & vendor harus jadi AWAITING**
                if ($newTab == SpbProject::TAB_SUBMIT) {
                    $status = ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;
                } else {
                    // **Jika undo dari PAID atau PAYMENT_REQUEST, status menyesuaikan due date**
                    $status = $nowDate->isSameDay($productDueDate) ? ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT
                        : ($nowDate->gt($productDueDate) ? ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT
                        : ProductCompanySpbProject::TEXT_OPEN_PRODUCT);
                }

                // **Pastikan status produk & vendor benar-benar diupdate**
                $product->update([
                    'status_produk' => $status,
                    'status_vendor' => $status,
                ]);
            }

    
            // Update status SPB Project dan tab
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::AWAITING,  // Status diubah kembali ke AWAITING
                'tab_spb' => $newTab,  // Tab dikurangi satu tingkat
                'is_payment_vendor' => null, 
            ]);
    
            // Tambahkan log undo untuk kategori lain
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => $newTab,
                'name' => auth()->user()->name,
                'message' => 'SPB Project has been undone and reverted',
            ]);
    
            DB::commit();
    
            return MessageActeeve::success("SPB Project $docNoSpb has been undone successfully");
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

   /*  public function undo($docNoSpb)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::with('productCompanySpbprojects')->where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        $userRole = auth()->user()->role_id;

        // Jika bukan OWNER dan SPB sudah PAID, tidak bisa melakukan undo
        if ($userRole !== Role::OWNER && $spbProject->tab_spb == SpbProject::TAB_PAID) {
            return MessageActeeve::error("Unauthorized! Only OWNER can undo from PAID.");
        }

        // Jika sudah SUBMIT, tidak bisa di-undo lebih jauh
        if ($spbProject->tab_spb == SpbProject::TAB_SUBMIT) {
            return MessageActeeve::warning("Cannot undo because tab is already at SUBMIT.");
        }

        try {
            // Tentukan tab sebelumnya berdasarkan tab saat ini
            $previousTab = match ($spbProject->tab_spb) {
                SpbProject::TAB_PAID => SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAYMENT_REQUEST => SpbProject::TAB_VERIFIED,
                SpbProject::TAB_VERIFIED => SpbProject::TAB_SUBMIT,
                default => SpbProject::TAB_SUBMIT,
            };

            // Pastikan status SPB tidak kembali ke AWAITING kecuali jika di tab SUBMIT
            $spbStatus = $spbProject->spbproject_status_id;
            if ($previousTab == SpbProject::TAB_SUBMIT) {
                $spbStatus = SpbProject_Status::AWAITING;
            }

            // Reset status produk & vendor hanya jika belum PAID atau REJECTED
            foreach ($spbProject->productCompanySpbprojects as $product) {
                if (!in_array($product->status_produk, [ProductCompanySpbProject::TEXT_PAID_PRODUCT, ProductCompanySpbProject::TEXT_REJECTED_PRODUCT])) {
                    $productDueDate = Carbon::parse($product->due_date)->timezone('Asia/Jakarta');
                    $nowDate = Carbon::now('Asia/Jakarta');

                    $status = match (true) {
                        $nowDate->isSameDay($productDueDate) => ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT,
                        $nowDate->gt($productDueDate) => ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                        default => ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                    };

                    $product->update([
                        'status_produk' => $status,
                        'status_vendor' => $status,
                    ]);
                }
            }

            // Update tab_spb & status SPB Project
            $spbProject->update([
                'tab_spb' => $previousTab,
                'spbproject_status_id' => $spbStatus,
            ]);

            // Tambahkan log undo
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => $previousTab,
                'name' => auth()->user()->name,
                'message' => "SPB Project has been undone and moved back to previous tab: " . $previousTab,
            ]);

            DB::commit();

            return MessageActeeve::success("SPB Project $docNoSpb has been undone successfully and reverted to previous tab.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    } */


    public function reject($docNoSpb, Request $request)
    {

        if (!auth()->user()->hasRole(Role::OWNER) && !auth()->user()->hasRole(Role::SUPERVISOR)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only users with the Owner or Supervisor role can reject this SPB.',
            ], 403);
        }

        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $SpbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$SpbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Pastikan reject_note ada dalam request
        if (!$request->has('note') || empty($request->note)) {
            return MessageActeeve::error('Reject note is required!');
        }

        try {
            $isFlashCash = $SpbProject->spbproject_category_id == SpbProject_Category::FLASH_CASH;

            // Update status dan tab di SpbProject
            $SpbProject->update([
                'spbproject_status_id' => SpbProject_Status::REJECTED, // Status diubah menjadi REJECTED
                'reject_note' => $request->note,
                'tab_spb' => $isFlashCash ? SpbProject::TAB_PAYMENT_REQUEST : SpbProject::TAB_SUBMIT, 
            ]);

             // Mengupdate status produk yang terkait dengan SPB Project menjadi REJECTED
            $SpbProject->productCompanySpbprojects()->update([
                'status_produk' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT, 
                'status_vendor' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT, 
            ]);

            // Mengecek apakah log dengan tab SUBMIT sudah ada sebelumnya untuk user yang sama
            $existingLog = $SpbProject->logs()->where('tab_spb', $isFlashCash ? SpbProject::TAB_PAYMENT_REQUEST : SpbProject::TAB_SUBMIT)
            ->where('name', auth()->user()->name)
            ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been rejected', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                    'reject_note' => $request->note, // Simpan note_reject dari request
                ]);
            } else {
                // Menyimpan log untuk reject jika belum ada
                $SpbProject->logs()->create([
                    'tab_spb' => $isFlashCash ? SpbProject::TAB_PAYMENT_REQUEST : SpbProject::TAB_SUBMIT,
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been rejected', // Pesan untuk aksi reject
                    'reject_note' => $request->note, // Simpan note_reject dari request
                ]);
            }

            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNoSpb has been rejected");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function rejectproduk(RejectProdukRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
                $noteRejectProduk = $produkData['note_reject_produk']; // Catatan penolakan produk

                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();

                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }

                // Perbarui status produk menjadi rejected dan tambahkan note penolakan
                $product->update([
                    'status_produk' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT,
                    'status_vendor' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT,
                    'note_reject_produk' => $noteRejectProduk,  // Catatan penolakan
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been updated with rejection notes.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function deleteProduk($id) {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return MessageActeeve::notFound('SPB Project not found!');
            }
    
            // Periksa apakah ada produk yang ingin dihapus
            $produkIds = collect(request()->produk)->pluck('produk_id');
            $vendorIds = collect(request()->produk)->pluck('vendor_id');
    
            // Hapus produk terkait dari tabel pivot
            $spbProject->productCompanySpbprojects()
                ->whereIn('produk_id', $produkIds)
                ->whereIn('company_id', $vendorIds)
                ->delete();
    
            DB::commit();
            return MessageActeeve::success("Produk terkait di SPB Project {$id} telah dihapus.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
    
    public function activateproduk(ActivateProdukRequest $request, $id) {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
    
                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();
    
                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }
    
                // Update atribut produk berdasarkan request
                $product->update([
                    'harga' => $produkData['harga'],       // Update harga
                    'stok' => $produkData['stok'],         // Update stok
                    'tax_ppn' => $produkData['tax_ppn'], 
                    'description' => $produkData['description'],   
                    'ongkir' => $produkData['ongkir'],     // Update ongkir
                    'date' => $produkData['date'],         // Update date
                    'due_date' => $produkData['due_date'], // Update due_date
                ]);
    
                // Periksa jika produk sebelumnya berstatus rejected dan ubah statusnya menjadi awaiting
                if ($product->status_produk === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    // Jika statusnya rejected, ubah menjadi awaiting
                    $product->update([
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, 
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        'note_reject_produk' => null,  // Hapus catatan penolakan
                    ]);
                } else {
                    // Jika produk tidak ditolak, langsung ubah status menjadi awaiting
                    $product->update([
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                }
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been updated with awaiting status.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function activate(ActivateSpbRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Cari SpbProject berdasarkan doc_no_spb
            $SpbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$SpbProject) {
                return MessageActeeve::notFound('Data tidak ditemukan!');
            }

            // Pastikan bahwa SPB Project status sebelumnya adalah REJECTED
            if ($SpbProject->spbproject_status_id != SpbProject_Status::REJECTED) {
                return MessageActeeve::error('SPB Project tidak dalam status REJECTED!');
            }

            $company_id = $request->vendor_borongan_id;

            // Melakukan update terhadap SpbProject dengan data yang diterima pada request
            $SpbProject->update($request->only([
                'doc_no_spb',
                'doc_type_spb',
                'spbproject_category_id',
                'spbproject_status_id',
                'user_id',
                'project_id',
                'unit_kerja',
                'nama_toko',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
                'harga_total_pembayaran_borongan_spb',
                'type_termin_spb',
                'company_id',
                'vendor_borongan_id',
            ]));

            if ($SpbProject->spbproject_category_id == SpbProject_Category::BORONGAN && $company_id) {
                $SpbProject->company_id = $company_id; // Update company_id dengan vendor_borongan_id
                $SpbProject->save();
            }

            // Menghapus produk lama yang terkait dengan SPB Project sebelum mengaktifkannya
            // $SpbProject->products()->detach();

            $isFlashCash = $SpbProject->spbproject_category_id == SpbProject_Category::FLASH_CASH;

            // Logika status berdasarkan tanggal berakhir SPB untuk kategori FLASH_CASH
            if ($isFlashCash) {
                // Periksa tanggal berakhir SPB
                $dueDate = Carbon::parse($SpbProject->tanggal_berahir_spb);
                $nowDate = Carbon::now();

                // Tentukan status berdasarkan tanggal
                if ($nowDate->isSameDay($dueDate)) {
                    $spbStatus = SpbProject_Status::DUEDATE; // Status Due Date
                } elseif ($nowDate->gt($dueDate)) {
                    $spbStatus = SpbProject_Status::OVERDUE; // Status Overdue
                } elseif ($nowDate->lt($dueDate)) {
                    $spbStatus = SpbProject_Status::OPEN; // Status Open
                }
            } else {
                // Untuk kategori lainnya, langsung set status ke AWAITING
                $spbStatus = SpbProject_Status::AWAITING;
            }

            // Pastikan reject_note dihapus saat SPB Project diaktifkan
            $SpbProject->update([
                'spbproject_status_id' => $spbStatus,// Status diubah menjadi AWAITING
                'tab_spb' => $isFlashCash ? SpbProject::TAB_PAYMENT_REQUEST : SpbProject::TAB_SUBMIT, 
                'reject_note' => null, // Menghapus reject note yang sebelumnya
                'type_project' => $request->type_project,
            ]);

            // Ambil produk yang baru dari request
            $produkData = $request->input('produk_data', []);

            // Ambil semua produk yang saat ini terkait dengan SPB Project
            $existingProducts = $SpbProject->productCompanySpbprojects()->pluck('produk_id')->toArray();

            // Ambil ID produk dari request
            $newProductIds = collect($produkData)->pluck('produk_id')->toArray();

            // Hapus produk lama yang tidak ada di request baru
            $productsToDelete = array_diff($existingProducts, $newProductIds);
            if (!empty($productsToDelete)) {
                $SpbProject->productCompanySpbprojects()
                    ->whereIn('produk_id', $productsToDelete)
                    ->delete();
            }

            // Proses produk dari request
            foreach ($produkData as $item) {
                $vendorId = $item['vendor_id'];
                $productId = $item['produk_id'];

                // Periksa apakah produk sudah ada
                $existingProduct = $SpbProject->productCompanySpbprojects()
                    ->where('company_id', $vendorId)
                    ->where('produk_id', $productId)
                    ->first();

                if ($existingProduct) {
                    // Update produk yang ada
                    $existingProduct->update([
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'ppn' => $item['tax_ppn'] ?? 0,
                        'description' => $item['description'], 
                        'ongkir' => $item['ongkir'] ?? 0,
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                } else {
                    // Tambahkan produk baru
                    ProductCompanySpbProject::create([
                        'spb_project_id' => $SpbProject->doc_no_spb,
                        'produk_id' => $productId,
                        'company_id' => $vendorId,
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'ppn' => $item['tax_ppn'] ?? 0,
                        'description' => $item['description'], 
                        'ongkir' => $item['ongkir'] ?? 0,
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                        'status_vendor' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                }
            }

            $statusName = SpbProject_Status::find($SpbProject->spbproject_status_id)->name;

            // Tentukan pesan log sesuai dengan kategori SPB
            $message = $isFlashCash 
                ? "SPB Project $docNo dengan jenis FLASH CASH telah diaktifkan dan statusnya sekarang '$statusName' dengan tab 'Payment Request'." 
                : "SPB Project $docNo telah diaktifkan dan statusnya sekarang '$statusName'.";


             // Menambahkan log untuk aksi activate
            $SpbProject->logs()->create([
                'tab_spb' => $isFlashCash ? SpbProject::TAB_PAYMENT_REQUEST : SpbProject::TAB_SUBMIT, 
                'name' => auth()->user()->name, // Nama pengguna yang melakukan aktivasi
                'message' => $message,// Pesan log
                'created_at' => now(),
            ]);


            // Commit transaksi jika semua berhasil
            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNo telah diaktifkan dan statusnya sekarang '$statusName'.");

        } catch (\Throwable $th) {
            // Rollback transaksi jika ada error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function request($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $SpbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$SpbProject) {
            return MessageActeeve::notFound('Data not found!');
        }
    
        try {
            // Menyimpan atau memperbarui log untuk aksi request dengan tab yang sesuai
            $existingLog = $SpbProject->logs()->where('tab_spb', SpbProject::TAB_PAYMENT_REQUEST)
                                                ->where('name', auth()->user()->name)
                                                ->first();
    
            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been requested for payment', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                ]);
            } else {
                // Menyimpan log untuk aksi request jika belum ada
                $SpbProject->logs()->create([
                    'tab_spb' => SpbProject::TAB_PAYMENT_REQUEST, // Tab PAYMENT_REQUEST
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been requested for payment', // Pesan log
                ]);
            }
    
            // Memperbarui tab di SpbProject menjadi TAB_PAYMENT_REQUEST
            $SpbProject->update([
                'tab_spb' => SpbProject::TAB_PAYMENT_REQUEST,
            ]);
    
            DB::commit();
    
            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNoSpb has been requested for payment");
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function payment(PaymentRequest $request, $docNo)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Menyimpan atau memperbarui log untuk aksi pembayaran dengan tab yang sesuai
            $existingLog = $spbProject->logs()->where('tab_spb', SpbProject::TAB_PAID)
                                                ->where('name', auth()->user()->name)
                                                ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Menyimpan log untuk aksi pembayaran jika belum ada
                $spbProject->logs()->create([
                    'tab_spb' => SpbProject::TAB_PAID,
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project has been paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Memperbarui status SpbProject menjadi PAID dan mengubah tab menjadi TAB_PAID
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::PAID,
                'tab_spb' => SpbProject::TAB_PAID,
                'updated_at' => now(),
            ]);

             // Update status produk terkait dengan SpbProject menjadi PAID
            $spbProject->productCompanySpbprojects()->update([
                'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT, // Update status produk ke PAID
            ]);

            // Menyimpan file attachment jika ada
            if ($request->hasFile('attachment_file_spb')) {
                foreach ($request->file('attachment_file_spb') as $key => $file) {
                    // Periksa apakah file terdeteksi dan valid
                    if ($file->isValid()) {
                        $this->saveDocument($spbProject, $file, $key + 1);
                    } else {
                        return MessageActeeve::error('File upload failed');
                    }
                }
            } else {
                return MessageActeeve::error('No file attached');
            }

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment successfully processed");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function paymentVendor(PaymentVendorRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Cari SpbProject berdasarkan doc_no_spb
            $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$spbProject) {
                return MessageActeeve::notFound('SPB Project not found!');
            }

            // Validasi dan ambil tanggal pembayaran dari request (input manual)
            $paymentDate = $request->input('payment_date');
            if (!$paymentDate) {
                return MessageActeeve::error('Payment date is required!');
            }

            // Menyimpan file pembayaran jika ada
            $filePaymentPath = null;
            if ($request->hasFile('file_payment')) {
                // Pastikan file disimpan di storage yang benar
                $filePaymentPath = $request->file('file_payment')->store(ProductCompanySpbProject::FILE_PEMBAYARAN_VENDOR, 'public');
            }

            // Update SPB Project (jika ada perubahan pada payment_date dan file_payment)
            $spbProject->timestamps = false; 
            $spbProject->update([
                'payment_date' => $paymentDate,
                'file_payment' => $filePaymentPath, // Jika ada file pembayaran
                'updated_at' => $paymentDate, // Menggunakan payment_date untuk updated_at
                'is_payment_vendor' => 1, // 1 True
            ]);

            // Ambil vendor_id dari request dan anggap itu adalah company_id
            $companyId = $request->input('vendor_id'); // Menganggap vendor_id merujuk ke company_id

            // Update status produk menjadi "Paid" dan simpan payment_date serta file_payment
            $spbProject->productCompanySpbprojects()
                ->where('company_id', $companyId)  // Menggunakan company_id
                ->whereNotIn('status_produk', [
                ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                    ProductCompanySpbProject::TEXT_REJECTED_PRODUCT,
                ])  // Pastikan hanya produk yang belum dibayar atau ditolak
                ->update([
                    'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                    'status_vendor' => ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                    'payment_date' => $paymentDate,
                    'file_payment' => $filePaymentPath,  // Jika ada file pembayaran
                ]);

            // Cek jumlah vendor yang masih ada dalam SPB project
            $remainingVendors = $spbProject->productCompanySpbprojects()
                ->whereNotIn('status_produk', [
                    ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                ])
                ->count();

            // Jika sisa vendor hanya 1 dan vendor sudah melakukan payment request
            if ($remainingVendors == 0) {
                $spbProject->timestamps = false; 
                $spbProject->update([
                    'spbproject_status_id' => SpbProject_Status::PAID,
                    'tab_spb' => SpbProject::TAB_PAID, 
                    'payment_date' => $paymentDate,
                    'updated_at' => $paymentDate, 
                ]);

                // Menyimpan log dengan tab 'PAID'
                $logMessage = 'SPB Project payment completed, all products are paid.';
                $existingLog = $spbProject->logs()
                    ->where('tab_spb', SpbProject::TAB_PAYMENT_REQUEST)
                    ->where('name', auth()->user()->name)
                    ->first();

                if ($existingLog) {
                    $existingLog->update([
                        'message' => $logMessage,
                        'tab_spb' => SpbProject::TAB_PAID,  // Ganti tab menjadi PAID
                        'updated_at' => $paymentDate, // Menggunakan payment_date untuk updated_at
                    ]);
                } else {
                    $spbProject->logs()->create([
                        'tab_spb' => SpbProject::TAB_PAID,  // Ganti tab menjadi PAID
                        'name' => auth()->user()->name,
                        'message' => $logMessage,
                        'created_at' => $paymentDate, // Menggunakan payment_date untuk created_at
                        'updated_at' => $paymentDate, // Menggunakan payment_date untuk updated_at
                    ]);
                }
            }

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment processed successfully, status updated to 'Paid'.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function updatePaymentFlasInv(UpdatePaymentFlashInvRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Validasi role
            if (!auth()->user()->hasRole(Role::FINANCE) && !auth()->user()->hasRole(Role::OWNER) && !auth()->user()->hasRole(Role::ADMIN)) {
                return MessageActeeve::forbidden('Only users with the Finance, Owner, or Admin role can update payments.');
            }

            // Cari SPB berdasarkan doc_no_spb
            $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$spbProject) {
                return MessageActeeve::notFound('Data not found!');
            }

            // Format updated_at hanya Y-m-d (tanpa jam)
            $updatedAt = Carbon::parse($request->updated_at)->format('Y-m-d');

            $updateFields = [
                'updated_at' => $updatedAt,
            ];

            // Simpan file attachment jika ada
            if ($request->hasFile('attachment_file_spb')) {
                // Hapus dokumen lama dari DB dan storage
                foreach ($spbProject->documents as $document) {
                    if (Storage::disk('public')->exists($document->file_path)) {
                        Storage::disk('public')->delete($document->file_path);
                    }
                    $document->delete();
                }

                // Simpan file baru
                foreach ($request->file('attachment_file_spb') as $key => $file) {
                    if ($file->isValid()) {
                        $this->saveDocument($spbProject, $file, $key + 1);
                    } else {
                        return MessageActeeve::error('File upload failed');
                    }
                }
            }


            // Simpan perubahan ke database
            $spbProject->update($updateFields);

            // Tambahkan log update
            $spbProject->logs()->create([
                'tab_spb' => $spbProject->tab_spb,
                'name' => auth()->user()->name,
                'message' => 'SPB Flash/Invoice payment updated.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return MessageActeeve::success("SPB $docNo payment updated successfully (Flash/Invoice)");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function updatepayment(PaymentRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Validasi apakah user memiliki peran Finance atau Owner
            if (!auth()->user()->hasRole(Role::FINANCE) && !auth()->user()->hasRole(Role::OWNER) && !auth()->user()->hasRole(Role::ADMIN)) {
                return MessageActeeve::forbidden('Only users with the Finance, Owner, or Admin role can update payments.');
            }

            // Cari SpbProject berdasarkan doc_no_spb
            $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$spbProject) {
                return MessageActeeve::notFound('Data not found!');
            }

            $actorField = null;
          /*   if (auth()->user()->hasRole(Role::FINANCE)) {
                $actorField = 'know_finance';
            } elseif (auth()->user()->hasRole(Role::OWNER)) {
                $actorField = 'request_owner';
            } */

            // Logika untuk Borongan
            if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN) {
                $updateFields = [
                    $actorField => auth()->user()->id,
                    'approve_date' => now(),
                    'updated_at' => $request->updated_at,
                ];

                $fileAttachmentId = null;

                // Menyimpan file attachment jika ada
                if ($request->hasFile('attachment_file_spb')) {
                    foreach ($request->file('attachment_file_spb') as $key => $file) {
                        if ($file->isValid()) {
                            $document = $this->saveDocument($spbProject, $file, $key + 1);
                            $fileAttachmentId = $document->id; // Ambil ID dokumen untuk termin
                        } else {
                            return MessageActeeve::error('File upload failed');
                        }
                    }
                }
            
                // Tambahkan data termin ke database jika ada
                if ($request->has('harga_termin_spb') && $request->has('deskripsi_termin_spb')) {
                    $newTermin = new SpbProjectTermin([
                        'doc_no_spb' => $docNo,
                        'harga_termin' => $request->harga_termin_spb,
                        'deskripsi_termin' => $request->deskripsi_termin_spb,
                        'type_termin_spb' => $request->type_termin_spb,
                        'tanggal' => $request->updated_at,
                        'file_attachment_id' => $fileAttachmentId, // Simpan ID file attachment ke termin
                    ]);
                    $newTermin->save();
            
                    $totalHargaTermin = $spbProject->termins->sum('harga_termin');
                    $updateFields['harga_termin_spb'] = $totalHargaTermin;  // Update field harga total termin
                    // $spbProject->harga_total_pembayaran_borongan_spb -= $request->harga_termin_spb;

                    $updateFields['deskripsi_termin_spb'] = $request->deskripsi_termin_spb;
                }

                if ($request->has('type_termin_spb')) {
                    $updateFields['type_termin_spb'] = $request->type_termin_spb;

                    // Pindahkan ke Tab Paid jika type_termin_spb = 2 (Lunas)
                    if ($request->type_termin_spb == SpbProject::TYPE_TERMIN_LUNAS) {
                        $updateFields['spbproject_status_id'] = SpbProject_Status::PAID;
                        $updateFields['tab_spb'] = SpbProject::TAB_PAID;
                    } else {
                        // Tetap di Tab Payment Request jika belum lunas
                        $updateFields['tab_spb'] = SpbProject::TAB_PAYMENT_REQUEST;
                    }
                }

               // Menambahkan atau memperbarui log untuk Borongan
                $logMessage = $request->type_termin_spb == SpbProject::TYPE_TERMIN_LUNAS
                ? 'SPB Project Borongan payment fully paid.'
                : 'SPB Project Borongan payment in progress (termin not yet fully paid).';

                $existingLog = $spbProject->logs()
                ->where('tab_spb', $updateFields['tab_spb'])
                ->where('name', auth()->user()->name)
                ->first();

                if ($existingLog) {
                $existingLog->update([
                    'message' => $logMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                } else {
                    $spbProject->logs()->create([
                        'tab_spb' => $updateFields['tab_spb'],
                        'name' => auth()->user()->name,
                        'message' => $logMessage,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update SPB Project
                $spbProject->update($updateFields);

            } else {
                // Logika untuk Non-Borongan
                $existingLog = $spbProject->logs()
                    ->where('tab_spb', SpbProject::TAB_PAID)
                    ->where('name', auth()->user()->name)
                    ->first();

                if ($existingLog) {
                    // Jika log sudah ada, update pesan log yang sesuai
                    $existingLog->update([
                        'message' => 'SPB Project payment paid',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Menyimpan log untuk aksi pembayaran jika belum ada
                    $spbProject->logs()->create([
                        'tab_spb' => SpbProject::TAB_PAID,
                        'name' => auth()->user()->name,
                        'message' => 'SPB Project payment paid',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update SPB Project
                $spbProject->update([
                    'spbproject_status_id' => SpbProject_Status::PAID,
                    'tab_spb' => SpbProject::TAB_PAID,
                    $actorField => $actorField ? auth()->user()->id : null,
                    'approve_date' => now(),
                    'updated_at' => $request->updated_at,
                    'is_payment_vendor' => 0, // 0 False
                ]);

                // Update status produk
                /* $spbProject->productCompanySpbprojects()->update([
                    'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                    'payment_date' => $request->updated_at,
                ]); */

                $spbProject->productCompanySpbprojects()->each(function($productCompanySpbProject) use ($request) {
                    if ($productCompanySpbProject->status_produk != ProductCompanySpbProject::TEXT_PAID_PRODUCT) {
                        // Jika status produk belum paid, update payment_date dan status_produk
                        $productCompanySpbProject->update([
                            'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                            'status_vendor' => ProductCompanySpbProject::TEXT_PAID_PRODUCT,
                            'payment_date' => $request->updated_at,  // Set payment_date ke updated_at
                        ]);
                    }
                });

                    // Menyimpan file attachment jika ada
                if ($request->hasFile('attachment_file_spb')) {
                    foreach ($request->file('attachment_file_spb') as $key => $file) {
                        if ($file->isValid()) {
                            $this->saveDocument($spbProject, $file, $key + 1);
                        } else {
                            return MessageActeeve::error('File upload failed');
                        }
                    }
                }
            } 

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment updated successfully");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    protected function saveDocument($spbProject, $file, $iteration)
    {
        // Menyimpan file dan mencatatkan path-nya
        $document = $file->store(SpbProject::ATTACHMENT_FILE_SPB, 'public');

        // Cek apakah file berhasil disimpan
        Log::info('Document file saved', [
            'doc_no_spb' => $spbProject->doc_no_spb,
            'file_name' => $spbProject->doc_no_spb . '.' . $iteration,
            'file_path' => $document,
        ]);

        // Menyimpan informasi dokumen ke dalam database
        return $spbProject->documents()->create([
            "doc_no_spb" => $spbProject->doc_no_spb,
            "file_name" => $spbProject->doc_no_spb . '.' . $iteration,
            "file_path" => $document,
        ]);
    }

    public function deleteDocument($id)
    {
        DB::beginTransaction();

        // Mencari data SPB berdasarkan ID
        $spbProject = DocumentSPB::find($id);
        if (!$spbProject) {
            return MessageActeeve::notFound('data not found!');
        }

        try {
            // Menghapus file dari storage
            Storage::delete($spbProject->file_path);

            // Soft delete untuk data SPB
            $spbProject->delete();

            // Commit transaksi
            DB::commit();
            return MessageActeeve::success("document $id delete successfully");
        } catch (\Throwable $th) {
            // Rollback jika terjadi error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }


    public function knowmarketing(Request $request, $docNoSpb )
    {
        DB::beginTransaction();
        // dd($request->user());

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh marketing
            $spbProject->update([
                "know_marketing" => auth()->user()->id, 
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now acknowledged by marketing. Acknowledged by: {$userRole['user_name']} ({$userRole['role_name']})");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function knowmarkepalagudang($docNoSpb)
    {
        DB::beginTransaction();

        // Periksa apakah pengguna yang sedang login adalah Kepala Gudang
        if (auth()->user()->role_id != Role::GUDANG) {
            return MessageActeeve::error('Access denied. Only Kepala Gudang can perform this action.');
        }

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh kepala gudang
            $spbProject->update([
                "know_kepalagudang" => auth()->user()->id, // Simpan ID user yang mengetahui proyek
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now acknowledged by Gudang.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function menyetujuiowner($docNoSpb)
    {
        DB::beginTransaction();

        // Periksa apakah pengguna yang sedang login adalah Kepala Gudang
        if (auth()->user()->role_id != Role::OWNER) {
            return MessageActeeve::error('Access denied. Only Kepala Gudang can perform this action.');
        }

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh kepala gudang
            $spbProject->update([
                "request_owner" => auth()->user()->id, // Simpan ID user yang mengetahui proyek
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now Accepted Owner.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    
}
