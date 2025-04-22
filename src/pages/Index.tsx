
import { useState, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Progress } from "@/components/ui/progress";
import { useToast } from "@/hooks/use-toast";
import { AlbumCard } from "@/components/AlbumCard";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";
import { RefreshCw, Info, Loader, StopCircle, Play, Plus } from "lucide-react";

type Album = {
  id: number;
  title: string;
  photoCount: number;
  coverImage: string;
  date: string;
};

type LoadingLog = {
  id: number;
  message: string;
  timestamp: string;
};

const DEMO_ALBUMS_PAGE_1: Album[] = [
  {
    id: 1,
    title: "Summer Vacation",
    photoCount: 42,
    coverImage: "/placeholder.svg",
    date: "2024-06-15"
  },
  {
    id: 2,
    title: "Family Gathering",
    photoCount: 78,
    coverImage: "/placeholder.svg",
    date: "2024-05-22"
  },
  {
    id: 3,
    title: "Nature Photography",
    photoCount: 53,
    coverImage: "/placeholder.svg",
    date: "2024-04-10"
  }
];

const DEMO_ALBUMS_PAGE_2: Album[] = [
  {
    id: 4,
    title: "Birthday Party",
    photoCount: 65,
    coverImage: "/placeholder.svg",
    date: "2024-03-30"
  },
  {
    id: 5,
    title: "Wedding Photos",
    photoCount: 112,
    coverImage: "/placeholder.svg",
    date: "2024-02-14"
  },
  {
    id: 6,
    title: "Vacation 2023",
    photoCount: 87,
    coverImage: "/placeholder.svg",
    date: "2023-12-25"
  }
];

const Index = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [albums, setAlbums] = useState<Album[]>([]);
  const [progress, setProgress] = useState(0);
  const [logs, setLogs] = useState<LoadingLog[]>([]);
  const [fetchedAlbumTitles, setFetchedAlbumTitles] = useState<string[]>([]);
  const [cancelLoading, setCancelLoading] = useState(false);
  const [hasNextPage, setHasNextPage] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedAlbums, setSelectedAlbums] = useState<number[]>([]);
  const [isBulkImporting, setIsBulkImporting] = useState(false);
  const [bulkProgress, setBulkProgress] = useState(0);

  const { toast } = useToast();
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const addLog = (message: string) => {
    setLogs(currentLogs => [
      ...currentLogs, 
      { 
        id: Date.now() + Math.random(), 
        message, 
        timestamp: new Date().toLocaleTimeString() 
      }
    ]);
  };

  const resetState = () => {
    setIsLoading(false);
    setProgress(0);
    setLogs([]);
    setAlbums([]);
    setFetchedAlbumTitles([]);
    setCancelLoading(false);
    setHasNextPage(true);
    setCurrentPage(1);
    setSelectedAlbums([]);
    setIsBulkImporting(false);
    setBulkProgress(0);
    if (intervalRef.current) clearInterval(intervalRef.current);
  };

  const handleStartLoading = () => {
    resetState();
    setIsLoading(true);
    addLog("Started loading albums...");
    let step = 0;
    setProgress(0);

    setFetchedAlbumTitles([]);
    setCancelLoading(false);

    addLog("Authenticating with Google Photos API...");

    intervalRef.current = setInterval(() => {
      step += 1;

      if (cancelLoading) {
        addLog("Loading cancelled by user.");
        setIsLoading(false);
        setProgress(0);
        setCancelLoading(false);
        if (intervalRef.current) clearInterval(intervalRef.current);
        return;
      }

      if (step === 1) {
        setProgress(20);
        addLog("Fetching album list from Google...");
      } else if (step === 2) {
        setProgress(40);
        addLog("Connected, beginning to retrieve albums...");
      } else if (step >= 3 && step < (3 + DEMO_ALBUMS_PAGE_1.length)) {
        const albumIdx = step - 3;
        const album = DEMO_ALBUMS_PAGE_1[albumIdx];
        setFetchedAlbumTitles(prev => [...prev, album.title]);
        addLog(`Album "${album.title}" found (${album.photoCount} photos)`);
        setProgress(Math.min(60 + albumIdx * 10, 95));
      } else if (step === 3 + DEMO_ALBUMS_PAGE_1.length) {
        setProgress(100);
        addLog("First page of albums loaded successfully!");
        setAlbums(DEMO_ALBUMS_PAGE_1);
        setIsLoading(false);
        toast({
          title: "Albums Loaded",
          description: `Successfully loaded page ${currentPage} (${DEMO_ALBUMS_PAGE_1.length} albums)`,
        });
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    }, 700);
  };

  const handleLoadMore = () => {
    setIsLoading(true);
    addLog("Loading more albums...");
    let step = 0;
    setProgress(0);

    addLog("Fetching next page of albums...");

    intervalRef.current = setInterval(() => {
      step += 1;

      if (cancelLoading) {
        addLog("Loading cancelled by user.");
        setIsLoading(false);
        setProgress(0);
        setCancelLoading(false);
        if (intervalRef.current) clearInterval(intervalRef.current);
        return;
      }

      if (step === 1) {
        setProgress(30);
        addLog("Retrieving next page of albums...");
      } else if (step >= 2 && step < (2 + DEMO_ALBUMS_PAGE_2.length)) {
        const albumIdx = step - 2;
        const album = DEMO_ALBUMS_PAGE_2[albumIdx];
        setFetchedAlbumTitles(prev => [...prev, album.title]);
        addLog(`Album "${album.title}" found (${album.photoCount} photos)`);
        setProgress(Math.min(40 + albumIdx * 15, 95));
      } else if (step === 2 + DEMO_ALBUMS_PAGE_2.length) {
        setProgress(100);
        addLog("Next page of albums loaded successfully!");
        setAlbums(prev => [...prev, ...DEMO_ALBUMS_PAGE_2]);
        setCurrentPage(prev => prev + 1);
        setHasNextPage(false);  // No more pages in our demo
        setIsLoading(false);
        toast({
          title: "More Albums Loaded",
          description: `Successfully loaded page ${currentPage + 1} (${DEMO_ALBUMS_PAGE_2.length} more albums)`,
        });
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    }, 700);
  };

  const handleStopLoading = () => {
    setCancelLoading(true);
    setIsLoading(false);
    if (intervalRef.current) clearInterval(intervalRef.current);
    addLog("Stopped album loading.");
  };

  const toggleAlbumSelection = (albumId: number) => {
    setSelectedAlbums(prev => {
      if (prev.includes(albumId)) {
        return prev.filter(id => id !== albumId);
      } else {
        return [...prev, albumId];
      }
    });
  };

  const handleSelectAll = () => {
    if (selectedAlbums.length === albums.length) {
      // Deselect all
      setSelectedAlbums([]);
    } else {
      // Select all
      setSelectedAlbums(albums.map(album => album.id));
    }
  };

  const handleBulkImport = () => {
    if (selectedAlbums.length === 0) {
      toast({
        title: "No Albums Selected",
        description: "Please select at least one album to import.",
        variant: "destructive",
      });
      return;
    }

    setIsBulkImporting(true);
    setBulkProgress(0);
    addLog(`Starting bulk import of ${selectedAlbums.length} albums...`);

    let importedCount = 0;
    let currentIndex = 0;

    const bulkImportInterval = setInterval(() => {
      if (currentIndex >= selectedAlbums.length) {
        clearInterval(bulkImportInterval);
        setIsBulkImporting(false);
        addLog(`Bulk import completed: ${importedCount} albums imported.`);
        toast({
          title: "Bulk Import Complete",
          description: `Successfully imported ${importedCount} albums.`,
        });
        return;
      }

      const albumId = selectedAlbums[currentIndex];
      const album = albums.find(a => a.id === albumId);
      
      if (album) {
        addLog(`Importing album "${album.title}"...`);
        
        // Simulate import success
        importedCount++;
        addLog(`Album "${album.title}" imported successfully.`);
      }

      currentIndex++;
      setBulkProgress(Math.round((currentIndex / selectedAlbums.length) * 100));
    }, 800);
  };

  useEffect(() => {
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, []);

  return (
    <div className="min-h-screen flex flex-col bg-gray-100 p-4">
      <div className="max-w-5xl w-full mx-auto bg-white rounded-lg shadow-lg p-6 mb-6">
        <h1 className="text-3xl font-bold text-center mb-6">WP Gallery Link</h1>
        <p className="text-lg text-center mb-8">
          A WordPress plugin that connects with Google Photos to create a custom post type for albums.
        </p>
        <div className="grid md:grid-cols-2 gap-6">
          <div className="bg-gray-50 p-4 rounded-lg">
            <h2 className="text-xl font-semibold mb-3">Key Features</h2>
            <ul className="list-disc pl-5 space-y-2">
              <li>Google Photos integration</li>
              <li>Custom post type for albums</li>
              <li>Import album titles, images, and dates</li>
              <li>Category organization</li>
              <li>Customizable display options</li>
              <li>Responsive gallery layout</li>
              <li><strong>New:</strong> Pagination support</li>
              <li><strong>New:</strong> Bulk album import</li>
            </ul>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg">
            <h2 className="text-xl font-semibold mb-3">Plugin Usage</h2>
            <p className="mb-3">
              After installing the plugin in WordPress:
            </p>
            <ol className="list-decimal pl-5 space-y-2">
              <li>Connect to Google Photos API</li>
              <li>Import albums from your account</li>
              <li>Organize with categories</li>
              <li>Display with shortcode: <code>[wp_gallery_link]</code></li>
              <li>Use bulk import for multiple albums</li>
            </ol>
          </div>
        </div>
        <div className="mt-8 p-4 bg-blue-50 rounded-lg">
          <h2 className="text-xl font-semibold mb-3">Installation</h2>
          <p className="mb-4">
            To use this plugin in a real WordPress site, download the plugin files and upload them
            to your WordPress plugins directory, then activate the plugin from the WordPress admin panel.
          </p>
        </div>
      </div>
      <div className="max-w-5xl w-full mx-auto bg-white rounded-lg shadow-lg p-6">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-bold">Albums Demo</h2>
          <div className="flex gap-2">
            {isLoading ? (
              <Button 
                variant="outline"
                onClick={handleStopLoading}
                className="flex items-center gap-1"
              >
                <StopCircle className="h-4 w-4" /> Stop
              </Button>
            ) : (
              <Button 
                variant="outline"
                onClick={handleStartLoading}
                className="flex items-center gap-1"
                disabled={isBulkImporting}
              >
                <Play className="h-4 w-4" /> Start
              </Button>
            )}
            <Button 
              onClick={resetState}
              className="flex items-center gap-1"
              variant="ghost"
              disabled={(isLoading && !cancelLoading) || isBulkImporting}
            >
              <RefreshCw className="h-4 w-4" /> Reset
            </Button>
          </div>
        </div>
        {isLoading && (
          <div className="space-y-4 mb-6">
            <div className="flex items-center justify-between mb-2">
              <p className="flex items-center gap-1">
                <Loader className="h-4 w-4 animate-spin text-blue-500" />
                Loading albums...
              </p>
              <span className="text-sm font-bold text-gray-500">{progress}%</span>
            </div>
            <Progress value={progress} className="h-2" />
            <Alert className="bg-blue-50 border-blue-200">
              <Info className="h-4 w-4" />
              <AlertTitle>Loading Status Log</AlertTitle>
              <AlertDescription>
                <div className="mt-2 max-h-40 overflow-y-auto border rounded-md p-2 bg-white">
                  {logs.length > 0 ? (
                    logs.map((log) => (
                      <div key={log.id} className="text-sm py-1 border-b border-gray-100 last:border-0">
                        <span className="text-gray-500 mr-2">[{log.timestamp}]</span>
                        {log.message}
                      </div>
                    ))
                  ) : (
                    <div className="text-sm py-1 text-gray-500">No logs yet...</div>
                  )}
                </div>
              </AlertDescription>
            </Alert>
            <div className="bg-gray-50 rounded p-4">
              <h4 className="font-medium mb-2">Albums Being Grabbed:</h4>
              {fetchedAlbumTitles.length > 0 ? (
                <ul className="list-disc ml-5 space-y-1">
                  {fetchedAlbumTitles.map((title, idx) => (
                    <li key={idx} className="text-sm text-gray-700">{title}</li>
                  ))}
                </ul>
              ) : (
                <div className="text-gray-400">No albums discovered yet...</div>
              )}
            </div>
            <div className="grid md:grid-cols-3 gap-4">
              {[1, 2, 3].map((i) => (
                <AlbumCard
                  key={i}
                  id={i}
                  title=""
                  photoCount={0}
                  coverImage=""
                  date=""
                  isLoading={true}
                />
              ))}
            </div>
          </div>
        )}
        {!isLoading && albums.length > 0 && (
          <>
            {/* Bulk actions UI */}
            <div className="mb-6 p-4 bg-gray-50 rounded-lg">
              <div className="flex flex-wrap justify-between items-center mb-4">
                <div className="flex items-center gap-3 mb-2 sm:mb-0">
                  <h3 className="font-semibold">Bulk Actions</h3>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={handleSelectAll}
                    disabled={isBulkImporting}
                  >
                    {selectedAlbums.length === albums.length ? "Deselect All" : "Select All"}
                  </Button>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm text-gray-600">
                    <strong>{selectedAlbums.length}</strong> albums selected
                  </span>
                  <Button 
                    onClick={handleBulkImport} 
                    disabled={selectedAlbums.length === 0 || isBulkImporting}
                    size="sm"
                    className="flex items-center gap-1"
                  >
                    <Plus className="h-4 w-4" /> Bulk Import
                  </Button>
                </div>
              </div>
              
              {/* Bulk progress bar */}
              {isBulkImporting && (
                <div className="mt-3">
                  <div className="flex items-center justify-between mb-2">
                    <p className="text-sm">Bulk import in progress...</p>
                    <span className="text-sm font-medium">{bulkProgress}%</span>
                  </div>
                  <Progress value={bulkProgress} className="h-2" />
                </div>
              )}
            </div>

            <div className="grid md:grid-cols-3 gap-4">
              {albums.map(album => (
                <div key={album.id} className="relative">
                  <div className={`absolute top-3 left-3 z-10 ${selectedAlbums.includes(album.id) ? 'bg-blue-500' : 'bg-gray-200'} w-6 h-6 rounded flex items-center justify-center cursor-pointer transition-colors`}
                    onClick={() => toggleAlbumSelection(album.id)}>
                    {selectedAlbums.includes(album.id) && (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                      </svg>
                    )}
                  </div>
                  <AlbumCard
                    id={album.id}
                    title={album.title}
                    photoCount={album.photoCount}
                    coverImage={album.coverImage}
                    date={album.date}
                    isLoading={false}
                  />
                </div>
              ))}
            </div>
            
            {hasNextPage && (
              <div className="mt-6 flex justify-center">
                <Button 
                  onClick={handleLoadMore} 
                  disabled={isLoading || isBulkImporting}
                  variant="outline"
                  className="flex items-center gap-1"
                >
                  <Plus className="h-4 w-4" /> Load More Albums
                </Button>
              </div>
            )}
          </>
        )}

        {!isLoading && albums.length === 0 && (
          <div className="py-8 text-center">
            <p className="text-gray-500">No albums loaded. Click the "Start" button to see a demo.</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default Index;
