
import { useState, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Progress } from "@/components/ui/progress";
import { useToast } from "@/hooks/use-toast";
import { AlbumCard } from "@/components/AlbumCard";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";
import { RefreshCw, Info, Loader, Stop, Play } from "lucide-react";

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

const DEMO_ALBUMS: Album[] = [
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

const Index = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [albums, setAlbums] = useState<Album[]>([]);
  const [progress, setProgress] = useState(0);
  const [logs, setLogs] = useState<LoadingLog[]>([]);
  const [fetchedAlbumTitles, setFetchedAlbumTitles] = useState<string[]>([]);
  const [cancelLoading, setCancelLoading] = useState(false);

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
    if (intervalRef.current) clearInterval(intervalRef.current);
  };

  // The new start/stop logic
  const handleStartLoading = () => {
    resetState();
    setIsLoading(true);
    addLog("Started loading albums...");
    let step = 0;
    setProgress(0);

    setFetchedAlbumTitles([]);
    setCancelLoading(false);

    // Simulate authenticating
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
      } else if (step >= 3 && step < (3 + DEMO_ALBUMS.length)) {
        // Simulate grabbing each album one by one.
        const albumIdx = step - 3;
        const album = DEMO_ALBUMS[albumIdx];
        setFetchedAlbumTitles(prev => [...prev, album.title]);
        addLog(`Album "${album.title}" found (${album.photoCount} photos)`);
        setProgress(Math.min(60 + albumIdx * 10, 95));
      } else if (step === 3 + DEMO_ALBUMS.length) {
        // Done
        setProgress(100);
        addLog("Albums loaded successfully!");
        setAlbums(DEMO_ALBUMS);
        setIsLoading(false);
        toast({
          title: "Albums Loaded",
          description: `Successfully loaded ${DEMO_ALBUMS.length} albums`,
        });
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    }, 700);
  };

  // Handle stop/cancel loading
  const handleStopLoading = () => {
    setCancelLoading(true);
    setIsLoading(false);
    if (intervalRef.current) clearInterval(intervalRef.current);
    addLog("Stopped album loading.");
  };

  // Clean up interval on unmount
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
                <Stop className="h-4 w-4" /> Stop
              </Button>
            ) : (
              <Button 
                variant="outline"
                onClick={handleStartLoading}
                className="flex items-center gap-1"
              >
                <Play className="h-4 w-4" /> Start
              </Button>
            )}
            <Button 
              onClick={resetState}
              className="flex items-center gap-1"
              variant="ghost"
              disabled={isLoading && !cancelLoading}
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
            {/* Real-time text list of album names as they are found */}
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
        {!isLoading && (
          <div className="grid md:grid-cols-3 gap-4">
            {albums.length > 0 ? (
              albums.map(album => (
                <AlbumCard
                  key={album.id}
                  id={album.id}
                  title={album.title}
                  photoCount={album.photoCount}
                  coverImage={album.coverImage}
                  date={album.date}
                  isLoading={false}
                />
              ))
            ) : (
              <div className="col-span-3 py-8 text-center">
                <p className="text-gray-500">No albums loaded. Click the "Start" button to see a demo.</p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default Index;
